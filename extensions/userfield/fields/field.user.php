<?php
	
	Class fieldUser extends Field {
		function __construct($parent){
			parent::__construct($parent);
			$this->_name = __('User');
		}

		public function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}
		
		public function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggle();
		}
		
		public function getToggleStates(){

		    $users = UserManager::fetch();
	
			$states = array();
			foreach($users as $u){
				$states[$u->id] = $u->getFullName();
			}
			
			return $states;
		}

		public function toggleFieldData($data, $newState){
			$data['user_id'] = $newState;
			return $data;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){	
			
			$status = self::__OK__;
			
			if(!is_array($data) && !is_null($data)) return array('user_id' => $data);
			
			if(empty($data)) return NULL;
			
			$result = array();
			foreach($data as $id) $result['user_id'][] = $id;

			return $result;
		}

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$value = (isset($data['user_id']) ? $data['user_id'] : NULL);
		
			$callback = Administration::instance()->getPageCallback();
			
			if ($this->get('default_to_current_user') == 'yes' && empty($data) && empty($_POST)) {
				$value = array(Administration::instance()->User->id);
			}
			
			if (!is_array($value)) {
				$value = array($value);
			}

		    $users = UserManager::fetch();
		
			$options = array();

			foreach($users as $u){
				$options[] = array($u->id, in_array($u->id, $value), $u->getFullName());
			}
			
			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';			
			
			$attr = array();
			
			if($this->get('allow_multiple_selection') == 'yes') $attr['multiple'] = 'multiple';
						
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, $attr));
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}
		
		public function prepareTableValue($data, XMLElement $link=NULL){
			
			if(!is_array($data['user_id'])) $data['user_id'] = array($data['user_id']);
			
			if(empty($data['user_id'])) return __('None');
			
			$value = array();

			foreach($data['user_id'] as $user_id){
				
				if(is_null($user_id)) continue;
				
				$user = new User($user_id);

				if($user instanceof User){
					
					if(is_null($link)){
						$a = Widget::Anchor(
							General::sanitize($user->getFullName()), 
							URL . '/symphony/system/users/edit/' . $user->get('id') . '/'
						);
						$value[] = $a->generate();
					}
					
					else{
						$value[] = $user->getFullName();
					}
				}
			}

			if(!is_null($link)){
				$link->setValue(General::sanitize(implode(', ', $value)));
				return $link->generate();
			}

			return (empty($value) ? __('None') : implode(', ', $value));
		}

		public function isSortable(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}
		
		public function canFilter(){
			return true;
		}
		
		public function canImport(){
			return true;
		}
		
		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`user_id` $order");
		}
		
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			
			if (self::isFilterRegex($data[0])) {
				$this->_key++;
				$pattern = str_replace('regexp:', '', $this->cleanValue($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.user_id REGEXP '{$pattern}'
				";
				
			} elseif ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{$this->_key}.user_id = '{$value}'
					";
				}
				
			} else {
				if (!is_array($data)) $data = array($data);
				
				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}
				
				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.user_id IN ('{$data}')
				";
			}
			
			return true;
		}
		
		public function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			$fields['default_to_current_user'] = ($this->get('default_to_current_user') ? $this->get('default_to_current_user') : 'no');			
			
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
					
		}

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
	        if(!is_array($data['user_id'])) $data['user_id'] = array($data['user_id']);

	        $list = new XMLElement($this->get('element_name'));
	        foreach($data['user_id'] as $user_id){
	            $user = new User($user_id);
	            $list->appendChild(new XMLElement('item', 
	                                    $user->getFullName(), 
	                                    array('id' => $user->id, 'username' => $user->username)));
	        }
	        $wrapper->appendChild($list);
	    }
			
		public function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
		}
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'related');

			## Allow multiple selection
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue(__('%s Allow selection of multiple users', array($input->generate())));
			$div->appendChild($label);	
			
			## Default to current logged in user
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][default_to_current_user]', 'yes', 'checkbox');
			if($this->get('default_to_current_user') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue(__('%s Select current user by default', array($input->generate())));
			$div->appendChild($label);								
				
			$wrapper->appendChild($div);	
			
			$this->appendShowColumnCheckbox($wrapper);
					
		}
		
		public function createTable(){
			return Symphony::Database()->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') ."` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `user_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `user_id` (`user_id`)
				) TYPE=MyISAM;"
				
			);
		}

		public function getExampleFormMarkup(){

		    $users = UserManager::fetch();
		
			$options = array();

			foreach($users as $u){
				$options[] = array($u->id, NULL, $u->getFullName());
			}
			
			$fieldname = 'fields['.$this->get('element_name').']';
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';			
			
			$attr = array();
			
			if($this->get('allow_multiple_selection') == 'yes') $attr['multiple'] = 'multiple';
						
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, $attr));
			
			return $label;
		}


	}

