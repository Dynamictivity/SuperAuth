# SuperCakeAuth

## Introduction
SuperCakeAuth is an extension to the core cakePHP authentication and acl behaviors and components.
Currently it supports full row-level acl, among a few other goodies.

## Current Features
 * Completely automagic row-level ACL (This took me a solid 6 months to perfect)
 * Remember me functionality (Inspiration from Google and The Bakery)
 * actsAs both Controlled(ACO) and Requester(ARO) (Thanks Ceeram)
 * parentNode() in the behavior by default, configurable via actsAs array, can be overidden in the model (Thanks Ceeram)
 * Permission caching - allows for quick permission-based queries (Thanks AD7Six)
 * Automagic aros_acos rows created for user_id passed in model data
 * Support for user belonging to multiple groups (currently untested)
 * Permissions returned with query in $results['Permissions'] (used to determine whether to show/hide crud links)

## Additional Information
 * PHP5
 * Should work with CakePHP 1.2 but untested
 
## Todo
 * If user doesn't have "create" access, don't let them create child acos
 * Permission management interface
 * Optimize when permission caching happens

## Issues
 * No current permission management interface. (You have to DIY)
 * Other unknown potential issues. (Please let me know)

## Installation Instructions
 * Drop everything where it goes, it will "replace" the core cakePHP systems, but don't worry, this is the most current Auth/ACL code from core, just extended for the extra functionality.
 * Add Auth/Acl/Session to your components array in appController, make sure to add Auth before ACL, or this system won't work properly.
 * Add Session/Acl to your helpers.
 
### Initialize Database Tables
 * If you are not already using ACL, follow the instructions here first: http://book.cakephp.org/view/1246/Getting-Started
 
Run the following queries in MySQL:
 
	ALTER TABLE  `aros_acos` ADD UNIQUE (
	`aro_id` ,
	`aco_id`
	);
 
	CREATE TABLE IF NOT EXISTS `permission_cache` (
	  `id` bigint(20) NOT NULL AUTO_INCREMENT,
	  `aro_id` int(11) DEFAULT NULL,
	  `aco_id` int(11) DEFAULT NULL,
	  `model` varchar(255) DEFAULT NULL,
	  `foreign_key` int(11) DEFAULT NULL,
	  `_create` tinyint(1) DEFAULT NULL,
	  `_read` tinyint(1) DEFAULT NULL,
	  `_update` tinyint(1) DEFAULT NULL,
	  `_delete` tinyint(1) DEFAULT NULL,
	  `rule_id` int(11) DEFAULT NULL,
	  `rule_aro_id` int(11) DEFAULT NULL,
	  `rule_aco_id` int(11) DEFAULT NULL,
	  `created` datetime DEFAULT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

### Setting Up Your Models

var $actsAs = array('Acl' => array('type' => 'requester', 'parentClass'=> 'Group', 'foreignKey' => 'group_id'));
 * Options are "controlled", "requester" or "both"
 
### Doing row-level-queries
The following is an example conditions array for all posts the user has at least read access on
 
	array(
		'conditions' => array(), //array of custom conditions
		'aclConditions' => array() // it's as simple as adding this, pretty simple eh?
	)
	
The following is an example conditions array for all posts the user has update access on
 
	array(
		'conditions' => array(), //array of custom conditions
		'aclConditions' => array('Permissions._update' => true) // it's as simple as adding this, pretty simple eh?
	)
	
### Using Returned Permissions In View Layer
When you do a query using row-level ACL, the permissions are returned in the results under the "Permissions" model.
For instance, if you have a table on your index, in your foreach you can do something like this:

	if ($post['Permissions']['_edit']) {
		echo $this->Html->link(__('Edit', true), array('action' => 'edit', $post['Post']['id']));
	}
	
### Using Remember Me Functionality
 * Just include a remember_me checkbox in your login form, and everything is automagic!

## Conclusion
That is the basic implementation for now, there is more to come. I am constantly working on this because I have a very permissions intensive application, and that is why I have had to develop all of this. If you have any feedback, questions, comments, contributions, updates, etc - please let me know. I would love to know what is right/wrong and what and how things can be improved.

Hopefully someday the core Auth/ACL will be able to do everything I am doing here.