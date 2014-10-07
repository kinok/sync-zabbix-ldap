<?php

return [
	/**
	 * ldap auth should be working on zabbix
	 */
	'ldap' => [
		'bind' => [
			'bindDn' => 'uid=readonly_user,ou=services,dc=dc_example,dc=com',
			'bindPassword' => 'readonly_password',
		],
		'url' => 'ldaps://ldap.example.com',
		'port' => 636,
		'base_dn' => 'cn=examplegroup,ou=groups,dc=2y-media,dc=com',
		'filter' => 'cn=*',
	],
	/**
	 * zabbix_example_user is used to authenticate to zabbix
	 */
	'zabbix' => [
		'user' => 'zabbix_example_user',
		'password' => 'zabbix_example_password',
		'url' => 'http://zabbix.example.com',
		'usrgrpid' => 7,
	],
];
