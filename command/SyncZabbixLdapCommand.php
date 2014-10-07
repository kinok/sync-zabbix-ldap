<?php
namespace SyncZabbixLdapCommand;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZabbixApiWrapper\ZabbixApiWrapper;
use LdapWrapper\LdapWrapper;

/**
 * Class SyncZabbixLdapCommand
 * @package SyncZabbixLdapCommand
 */
class SyncZabbixLdapCommand extends Command
{

	/**
	 * @var
	 */
	protected $_config;
	/**
	 * @var
	 */
	protected $_zabbixApi;
	/**
	 * @var array
	 */
	protected $_ldapUsers = [];
	/**
	 * @var array
	 */
	protected $_zabbixUsers = [];
	/**
	 * @var array
	 */
	protected $_zabbixUsersIds = [];
	/**
	 * @var array
	 */
	protected $_report = [];

	/**
	 * Execute symfony method override
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->prepare($input, $output);

		$this->getLdapUsers();
		$this->getZabbixUsers();

		$this->createUsers();
		$this->deleteUsers();

		$this->printReport($output);
	}

	/**
	 * pre execute method
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function prepare(InputInterface $input, OutputInterface $output)
	{
		$this->_startTime = microtime(true);
		$this->parseConfigFile($input, $output);
	}

	/**
	 * Render result in stdout
	 *
	 * @param OutputInterface $output
	 */
	protected function printReport(OutputInterface $output)
	{
		$table = $this->getHelper('table');
		$table
			->setRows([
				['User(s) created', $this->_report['created'] ? $this->_report['created'] : 0],
				['User(s) deleted', isset($this->_report['deleted']) ? $this->_report['deleted'] : 0],
				['', ''],
				['Elapsed time', round(microtime(true) - $this->_startTime, 2) . ' sec'],
			]);
		$table->render($output);
	}

	/**
	 * Get & include config php file
	 *
	 * @param InputInterface $input
	 * @throws \Exception
	 */
	protected function parseConfigFile(InputInterface $input)
	{
		$config = $input->getOption('config-file');
		if (!$config || !file_exists($config))
			throw new \Exception('Config file not found.');
		$this->_config = require_once $input->getOption('config-file');
	}

	/**
	 * Fetching Ldap users to be Sync'ed
	 */
	protected function getLdapUsers()
	{
		$ldap = new LdapWrapper($this->_config['ldap']['url'], $this->_config['ldap']['port'], $this->_config['ldap']['bind']);
		$data = $ldap->search($this->_config['ldap']['base_dn'], $this->_config['ldap']['filter']);
		$ldapUsers = [];
		if ($data['count'] === 1) {
			$members = end($data);
			if (isset($members['memberuid']) && $members['memberuid']['count'] !== 0)
				array_shift($members['memberuid']);

			$ldapUsers = $members['memberuid'];
		}

		$this->_ldapUsers = $ldapUsers;

		if (count($ldapUsers) === 0)
			throw new \Exception('There is no Ldap user (maybe can\'t fetch them). This would delete ALL zabbix users!');
	}

	/**
	 * Fetching Zabbix users to be sync'ed
	 */
	protected function getZabbixUsers()
	{
		$this->_zabbixApi = new ZabbixApiWrapper($this->_config['zabbix']);
		// User not to be modified
		$zabbixWhitelist = array_merge(['Admin', 'guest'], [$this->_config['zabbix']['user']]);
		$zabbixUserData = $this->_zabbixApi->request('user.get', ['output' => 'extend'])['result'];
		$this->_zabbixUsers = array_diff(array_column($zabbixUserData, 'alias'), $zabbixWhitelist);

		// Create users in zabbix
		foreach ($zabbixUserData as $user)
			$this->_zabbixUsersIds[$user['alias']] = $user['userid'];
	}

	/**
	 * Creates user in Zabbix
	 */
	protected function createUsers()
	{
		$memberToCreate = array_diff($this->_ldapUsers, $this->_zabbixUsers);
		foreach ($memberToCreate as $member) {
			$this->_zabbixApi->request('user.create', [
				'alias' => $member,
				'passwd' => md5(mt_rand() . date('u')),
				'usrgrps' => [
					'usrgrpid' => $this->_config['zabbix']['usrgrpid'],
				],

			]);
		}
		$this->_report['created'] = count($memberToCreate);
	}

	/**
	 * Deletes user in Zabbix
	 */
	protected function deleteUsers()
	{
		$zabbixIdsToDelete = [];
		foreach (array_intersect_key($this->_zabbixUsersIds, array_flip(array_diff($this->_zabbixUsers, $this->_ldapUsers))) as $userid) {
			$zabbixIdsToDelete[] = ['userid' => $userid];
		}
		$this->_zabbixApi->request('user.delete', $zabbixIdsToDelete);
		$this->_report['deleted'] = count($zabbixIdsToDelete);
	}

	/**
	 * Configure symfony command
	 */
	protected function configure()
	{
		$this
			->setName('sync:ldap-zabbix')
			->setDescription('Synchronize Ldap <-> Zabbix users.')
			->addOption(
				'config-file',
				'c',
				InputOption::VALUE_REQUIRED,
				'Config file'
			);
	}
}