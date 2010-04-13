<?php
/**
 * ACL behavior class.
 *
 * Enables objects to easily tie into an ACL system
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework (http://cakephp.org)
 * Copyright 2006-2010, Cake Software Foundation, Inc.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2010, Cake Software Foundation, Inc.
 * @link          http://cakephp.org CakePHP Project
 * @package       cake
 * @subpackage    cake.cake.libs.model.behaviors
 * @since         CakePHP v 1.2.0.4487
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Short description for file
 *
 * @package       cake
 * @subpackage    cake.cake.libs.model.behaviors
 */
class AclBehavior extends ModelBehavior {
	// row-level acl
	var $__userAros = null;

/**
 * Maps ACL type options to ACL models
 *
 * @var array
 * @access protected
 */
	var $__typeMaps = array('requester' => 'Aro', 'controlled' => 'Aco', 'both' => array('Aro', 'Aco'));

/**
 * Sets up the configuation for the model, and loads ACL models if they haven't been already
 *
 * @param mixed $config
 * @return void
 * @access public
 */
	function setup(&$model, $config = array()) {
		if (is_string($config)) {
			$config = array('type' => $config);
		}
		$this->settings[$model->alias] = array_merge(
			array('parentClass' => $model->alias, 'foreignKey' => 'parent_id', 'type' => 'controlled'),
			$config
		);
		$types = $this->__typeMaps[$this->settings[$model->alias]['type']];
		if (!class_exists('AclNode')) {
			require LIBS . 'model' . DS . 'db_acl.php';
		}
		if (!is_array($types)) {
			$types = array($types);
		}
		foreach($types as $type) {
			if (PHP5) {
				$model->{$type} = ClassRegistry::init($type);
			} else {
				$model->{$type} =& ClassRegistry::init($type);
			}
		}
		
		// row-level acl
		$this->model =& $model;
	}
	
	// row-level acl begin
	function beforefind(&$model) {
		$types = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		if (!is_array($types)) {
			$types = array($types);
		}
		if (in_array('Aco', $types)) {
			$model->bindModel(
				array(
					'belongsTo' => array(
						'Permissions' => array(
							'className' => 'PermissionCache',
							'foreignKey' => 'id',
							'conditions' => array(
								'and' => array(
									'Permissions.model' => $model->alias,
									'Permissions._read' => 1,
									'Permissions.aro_id' => $this->__userAros
								)
							),
							'fields' => array(
								'Permissions.id',
								'Permissions.aro_id',
								'Permissions.model',
								'Permissions.foreign_key',
								'Permissions._create',
								'Permissions._read',
								'Permissions._update',
								'Permissions._delete'
							)
						)
					)
				)
			);
		}
	}

    function setUserAros($userId = null) {
		$types = $this->__typeMaps[strtolower($this->settings[$this->model->alias]['type'])];
		if (!is_array($types)) {
			$types = array($types);
		}
    	
    	if (!$userId) {
		    return;
		}
		
		if (!in_array('Aro', $types)) {
			$Aros = Classregistry::init('Aro');
		} else {
			$Aros = $this->model->Aro;
		}
		
		$aros = $Aros->find('all',
			array(
			    'conditions' => array(
				'Aro.model' => 'User',
				'Aro.foreign_key' => $userId
		    ),
		    'fields' => array(
				'Aro.id',
				'Aro.model',
				'Aro.foreign_key'
		    )
		));
		
		foreach ($aros as $aro) {
		    $this->__userAros[] = $aro['Aro']['id'];
		}
    }

    function aclConditions($options = array()) {
		$settings = array(
		    'model' => $this->controller->modelClass,
		    'permissions' => null,
		    'conditions' => null,
		    'additional' => array(),
		    'fields' => array('DISTINCT id', 'name'),
		    'contain' => array(
			    'PermissionCache' => array(
					'fields' => array('foreign_key')
				),
			    'User' => array(
					'fields' => array('id', 'name')
				),
			    'Permission'
		    )
		);
	
		extract(Set::merge($settings, $options));
	
		$sql = array(
		    'conditions' => array(
			'or' => array(
			    'and' => array(
					'PermissionCache.aro_id' => $this->userAros['Ids'],
					'PermissionCache._read' => 1,
					$permissions
			    ),
			    $model . '.user_id' => User::get('id')
			),
			$conditions
		    ),
		    'fields' => $fields,
		    'contain' => $contain
		);
	
		return Set::merge($sql, $additional);
    }
    // row-level acl end

/**
 * Retrieves the Aro/Aco node for this model
 *
 * @param mixed $ref
 * @return array
 * @access public
 */
	function node(&$model, $ref = null, $type = null) {
		if (empty($type)) {
			$type = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
			if (is_array($type)) {
				trigger_error(__('AclBehavior is setup with more then one type, please specify type parameter for node()', true), E_USER_WARNING);
				return null;
			}
		}
		if (empty($ref)) {
			$ref = array('model' => $model->alias, 'foreign_key' => $model->id);
		}
		return $model->{$type}->node($ref);
	}

/**
 * Creates a new ARO/ACO node bound to this record
 *
 * @param boolean $created True if this is a new record
 * @return void
 * @access public
 */
	function afterSave(&$model, $created) {
		$types = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		if (!is_array($types)) {
			$types = array($types);
		}
		foreach ($types as $type) {
			$parent = $model->parentNode();
			if (!empty($parent)) {
				$parent = $this->node($model, $parent, $type);
			}
			$data = array(
				'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
				'model' => $model->alias,
				'foreign_key' => $model->id,
				'alias' => isset($model->data[$model->alias][$model->displayField]) ? $model->data[$model->alias][$model->displayField] : null
			);
			if (!$created) {
				$node = $this->node($model, null, $type);
				$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
			}
			$model->{$type}->create();
			$model->{$type}->save($data);
		}
	}

/**
 * Destroys the ARO/ACO node bound to the deleted record
 *
 * @return void
 * @access public
 */
	function afterDelete(&$model) {
		$types = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		if (!is_array($types)) {
			$types = array($types);
		}
		foreach ($types as $type) {
			$node = Set::extract($this->node($model, null, $type), "0.{$type}.id");
			if (!empty($node)) {
				$model->{$type}->delete($node);
			}
		}
	}


	function parentNode(&$model) {
		if (!$model->id && empty($model->data)) {
			return null;
		}
		$foreignKey = $this->settings[$model->alias]['foreignKey'];
		$data = $model->data;
		if (empty($data)) {
			$data = $model->read();
		}
		if (!isset($data[$model->alias][$foreignKey])) {
			$data[$model->alias][$foreignKey] = $model->field($foreignKey);
		}
		if (!array_key_exists($foreignKey, $data[$model->alias]) || empty($data[$model->alias][$foreignKey])) {
			return null;
		}
		return array($this->settings[$model->alias]['parentClass'] => array('id' => $data[$model->alias][$foreignKey]));
	}
}

?>