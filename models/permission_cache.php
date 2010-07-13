<?php
/* SVN FILE: $Id$ */
/**
 * Short description for permission_cache.php
 *
 * Long description for permission_cache.php
 *
 * PHP versions 4 and 5
 *
 * Copyright (c) 2008, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2008, Andy Dawson
 * @link          www.ad7six.com
 * @package       acl
 * @subpackage    acl.models
 * @since         v 1.0
 * @version       $Revision$
 * @modifiedby    $LastChangedBy$
 * @lastmodified  $Date$
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
/**
 * PermissionCache class
 *
 * Note this class extends Model, and not AppModel, so as not to inherit any unnecessary logic
 *
 * @uses          Model
 * @package       acl
 * @subpackage    acl.models
 */
class PermissionCache extends SuperAuthAppModel {
/**
 * name property
 *
 * @var string 'PermissionCache'
 * @access public
 */
	var $name = 'PermissionCache';
/**
 * Explicitly disable in-memory query caching
 *
 * @var boolean
 * @access public
 */
	var $cacheQueries = false;

/**
 * useTable property
 *
 * @var string 'permission_cache'
 * @access public
 */
	var $useTable = 'permission_cache';
	var $primaryKey = 'foreign_key';
	var $actsAs = array('Containable');

	var $belongsTo = array(
		'Aro' => array(
			'className' => 'Aro',
			'foreignKey' => 'aro_id'
		),
		'Aco' => array(
			'className' => 'Aco',
			'foreignKey' => 'aco_id'
		)
	);

/**
 * construct method
 *
 * Set the db config to use if appropriate
 *
 * @return void
 * @access private
 */
	function __construct() {
		$config = Configure::read('Acl.database');
		if (!empty($config)) {
			$this->useDbConfig = $config;
		}
		return parent::__construct();
	}
/**
 * bindForDisplay method
 *
 * @return void
 * @access public
 */
	function bindForDisplay() {
		$this->bindModel(array('belongsTo' => array(
			'Aco',
			'Aro',
			'Rule' => array('className' => 'Permission'),
			'RuleAco' => array('className' => 'Aco'),
			'RuleAro' => array('className' => 'Aro'),
		)), false);
	}
/**
 * clear method
 *
 * @param string $type
 * @param mixed $id
 * @return void
 * @access public
 */
	function clear($type = null, $id = null, $table = 'permission_cache') {
		$field = strtolower($type) . '_id';
		$conditions = null;
		if ($id && $type) {
			$conditions =  ' WHERE `' . $field . '` = '. $id;
		}
		$this->query('DELETE FROM ' . $table . $conditions . ';');
	}
/**
 * populate method
 *
 * @param string $type
 * @param mixed $id
 * @return void
 * @access public
 */
	function populate($type = 'Aco', $id = null, $clear = false, $table = 'permission_cache') {
		$field = low($type) . '_id';
		if ($clear) {
			$this->clear($type, $id, $table);
		}
		$alias = 'the' . $type;
		$date = date('Y-m-d H:i:s');
		$this->query("INSERT INTO $table
			(aro_id, aco_id, model, foreign_key, _create, _read, _update, _delete, rule_id, rule_aro_id, rule_aco_id, created)
			SELECT
				theAro.id as AroId,
				theAco.id as AcoId,
				theAco.model as AcoModel,
				theAco.foreign_key as AcoForeignKey,
				permissions._create as CanCreate,
				permissions._read as CanRead,
				permissions._update as CanEdit,
				permissions._delete as CanDelete,
				permissions.id as RuleId,
				permissions.aro_id as RuleAroId,
				permissions.aco_id as RuleAcoId,
				'$date'
			FROM
				acos AS theAco, aros AS theAro
			INNER JOIN
				aros_acos AS permissions ON (
					permissions.id = (
						SELECT
							permissions.id
						FROM
							aros_acos AS permissions
						INNER JOIN
							acos AS ruleAco ON (
								ruleAco.id = permissions.aco_id)
						INNER JOIN
							aros AS ruleAro ON (
								permissions.aro_id = ruleAro.id)
						WHERE
							ruleAco.lft <= theAco.lft AND
							ruleAco.rght >= theAco.rght AND
							theAro.lft >= ruleAro.lft AND
							theAro.rght <= ruleAro.rght
						ORDER BY
							ruleAro.lft DESC, ruleAco.lft DESC
						LIMIT 1
					)
				)
			WHERE
				$alias.id = $id
					AND NOT
						(theAco.model IS NULL)");
	}
/**
 * truncate method
 *
 * @return void
 * @access public
 */
	function truncate() {
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$this->query('TRUNCATE permission_cache');
	}
}