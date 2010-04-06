<?php
class SuperAclComponent extends Object {
    public $userAros = array();
    public $conditions = array();
    
	//called before Controller::beforeFilter()
	function initialize(&$controller, $settings = array()) {
		// saving the controller reference for later use
		$this->controller =& $controller;
	}

	//called after Controller::beforeFilter()
	function startup(&$controller) {
		if ($this->controller->Auth->user()) {
			$this->__getUserAros();
		}
		
		switch ($this->controller->action) {
		    case 'view':
			if (isset($this->params['pass'][0])) {
			    $this->conditions = array(
				'conditions' => array(
				    $this->controller->modelClass . '.id' => $this->controller->params['pass'][0]
				),
				'contain' => array(
				    'Owner' => array(
					'fields' => array('name')
				    )
				)
			    );
			}
			break;
		    case 'index':
			$activeFilter = array();
			$conditions = array();
	
			if (isset($this->controller->params['named']['view'])) {
			    $viewParam = $this->controller->params['named']['view'];
	
			    if ($viewParam == 'all') {
			    // no activeFilter
			    } elseif ($viewParam == 'inactive') {
				$activeFilter = array($this->controller->modelClass . '.is_active' => null);
			    }
			} else {
			    $activeFilter = array($this->controller->modelClass . '.is_active' => 1);
			}
	
			if (!empty($activeFilter)) {
			    $conditions = array(
				'conditions' => array(
				    $activeFilter
				)
			    );
			}
	
			$this->conditions = $this->aclConditions($conditions);
			break;
		}
	}

    function cachePermissions($conditions = null, $clear = false) {
		if ($conditions && !is_array($conditions)) {
		    return;
		}
	
		$PermissionCache = ClassRegistry::init('PermissionCache');
		if ($clear) {
		    $PermissionCache->deleteAll('1=1', false);
		}
	
		if (!$conditions && !$PermissionCache->find('count')) {
		    $aros = $this->controller->Acl->Aro->find('list');
		    $acos = $this->controller->Acl->Aro->find('count');
		    set_time_limit(max(count($aros) * $acos * 0.1, 30));
		    foreach ($aros as $id => $display) {
				$PermissionCache->populate('Aro', $id);
		    }
		}
	
		if (isset($conditions['aco_id'])) {
		    $PermissionCache->populate('Aco', $conditions['aco_id'], true);
		}
	
		if (isset($conditions['aro_id'])) {
		    $PermissionCache->populate('Aro', $conditions['aro_id'], true);
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
			    'Owner' => array(
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
			    $model . '.owner_id' => User::get('id')
			),
			$conditions
		    ),
		    'fields' => $fields,
		    'contain' => $contain
		);
	
		return Set::merge($sql, $additional);
    }

    function __getUserAros($userId = null) {
		if (!$userId) {
		    $userId = User::get('id');
		}
		
		$aros = $this->controller->Acl->Aro->find('all', array(
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
		    $this->userAros['Objects'][] = array(
				$aro['Aro']['model'] => array(
				    'id' => $aro['Aro']['foreign_key']
				)
		    );
		    $this->userAros['Ids'][$aro['Aro']['id']] = $aro['Aro']['id'];
		}
    }

	//called after Controller::beforeRender()
	function beforeRender(&$controller) {
	}

	//called after Controller::render()
	function shutdown(&$controller) {
	}

	//called before Controller::redirect()
	function beforeRedirect(&$controller, $url, $status=null, $exit=true) {
	}
}
?>