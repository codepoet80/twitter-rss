<?php
return array(
	'bearer_token' => "<YOURBEARERTOKENFROMTWITTER>",	//sign up for a Twitter developer account to get a token for their API v2
	'require_encoding' => false,	//if set to true, all query parameters (get and post) must be base64 encoded
	'access_control' => array (		//you can only have one set of access control rules, this is the default, see other examples below
		'anonymous' => '*'
	),
	'access_control_example1' => array ( //allow anyone to access any twitter feed
		'anonymous' => '*'
	),
	'access_control_example2' => array ( //allow anyone to access a specific list of twitter feeds, and a specific user any feed
		'anonymous' => array (
			'1463262475361533952',
			'90903190'
		),
		'clientsecret1' => '*',	//client secret name is any value you want and must be shared with the client to send with their request
	),
	'access_control_example3' => array ( //allow anyone to access a specific twitter feeds, and a specific user a different feed
		'anonymous' => array (
			'1463262475361533952',
		),
		'clientsecret1' => array (
			'90903190',
		),
	),
);
?>
