<?php
require_once('./Services/Form/classes/class.ilPropertyFormGUI.php');

/**
 * Class hubOriginObjectPropertiesFormGUI
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
abstract class hubOriginObjectPropertiesFormGUI extends ilPropertyFormGUI {

	/**
	 * @var
	 */
	protected $parent_gui;
	/**
	 * @var  ilCtrl
	 */
	protected $ctrl;
	/**
	 * @var
	 */
	protected $origin_properties;
	/**
	 * @var hubOrigin
	 */
	protected $origin;


	/**
	 * @return string
	 * @description return prefix of object_type like cat, usr, crs, mem
	 */
	abstract protected function getPrefix();


	/**
	 * @param           $parent_gui
	 * @param           $usage_type
	 * @param hubOrigin $origin
	 *
	 * @return bool|hubCategoryPropertiesFormGUI|hubCoursePropertiesFormGUI
	 */
	public static function getInstance($parent_gui, $usage_type, hubOrigin $origin) {
		require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/Category/class.hubCategoryPropertiesFormGUI.php');
		require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/Course/class.hubCoursePropertiesFormGUI.php');
		require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/User/class.hubUserPropertiesFormGUI.php');
		require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/Membership/class.hubMembershipPropertiesFormGUI.php');
		switch ($usage_type) {
			case hub::OBJECTTYPE_CATEGORY:
				return new hubCategoryPropertiesFormGUI($parent_gui, $origin);
			case hub::OBJECTTYPE_COURSE:
				return new hubCoursePropertiesFormGUI($parent_gui, $origin);
			case hub::OBJECTTYPE_USER:
				return new hubUserPropertiesFormGUI($parent_gui, $origin);
			case hub::OBJECTTYPE_MEMBERSHIP:
				return new hubMembershipPropertiesFormGUI($parent_gui, $origin);
		}

		return false;
	}


	public function __construct($parent_gui, hubOrigin $origin) {
		global $ilCtrl;
		$this->origin = $origin;
		$this->parent_gui = $parent_gui;
		$this->ctrl = $ilCtrl;
		$this->ctrl->saveParameter($parent_gui, 'origin_id');
		$this->pl = new ilHubPlugin();
		$this->origin_properties = hubOriginObjectProperties::getInstance($this->origin->getId());
		$this->initStandardFields();
		$this->initForm();
		$origin->getObject()->appendFieldsToPropForm($this);
		$this->personalizeFields();
	}


	private function personalizeFields() {
		foreach ($this->getItems() as $item) {
			/**
			 * @var ilRadioGroupInputGUI $item
			 * @var ilRadioOption        $op
			 */
			if (get_class($item) != 'ilFormSectionHeaderGUI') {
				$item->setPostVar($this->getPrefix() . '_' . $item->getPostVar());
				if (get_class($item) == 'ilRadioGroupInputGUI') {
					foreach ($item->getOptions() as $op) {
						foreach ($op->getSubItems() as $subItem) {
							$subItem->setPostVar($this->getPrefix() . '_' . $subItem->getPostVar());
						}
					}
				} else {
					foreach ($item->getSubItems() as $subItem) {
						$subItem->setPostVar($this->getPrefix() . '_' . $subItem->getPostVar());
					}
				}
			}
		}
	}


	private function initStandardFields() {
		$se = new ilSelectInputGUI($this->pl->txt('com_prop_link_to_origin'), 'origin_link');
		/**
		 * @var $origin hubOrigin
		 */
		$opt[0] = $this->pl->txt('none');
		foreach (hubOrigin::get() as $origin) {
			$opt[$origin->getId()] = $origin->getTitle();
		}
		$se->setOptions($opt);
		$this->addItem($se);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('com_prop_shortlink'), 'shortlink');
		$this->addItem($cb);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('com_prop_use_ext_status'), 'use_ext_status');
		$status_string = '';
		foreach (hubSyncHistory::getAllStatusAsArray() as $name => $int) { // FSX externer Status
			$status_string .= $name . ': ' . $int . ', ';
		}
		$cb->setInfo($status_string);
		// $this->addItem($cb);
	}


	/**
	 * @param ilPropertyFormGUI $form_gui
	 *
	 * @return ilPropertyFormGUI
	 */
	public function appendToForm(ilPropertyFormGUI $form_gui) {
		foreach ($this->getItems() as $item) {
			$form_gui->addItem($item);
		}

		return $form_gui;
	}


	/**
	 * @param $form_item
	 *
	 * @return mixed
	 */
	public function appendToItem($form_item) {
		foreach ($this->getItems() as $item) {
			$form_item->addSubItem($item);
		}

		return $form_item;
	}


	/**
	 * @description build FormElements
	 */
	abstract protected function initForm();


	public function fillForm() {
		$this->setValuesByArray($this->returnValuesArray());
	}


	/**
	 * @return mixed
	 * @description $array = array('title' => $this->origin->getTitle(), // [...]);
	 */
	public function returnValuesArray() {
		$array = array();
		/**
		 * @var $item    ilCheckboxInputGUI
		 * @var $subItem ilCheckboxInputGUI
		 */
		foreach ($this->getItems() as $item) {
			if (get_class($item) != 'ilFormSectionHeaderGUI') {
				$value = $this->origin_properties->getByKey($item->getPostVar(), true);
				if ($value) {
					$array[$item->getPostVar()] = $value;
				}
				if (get_class($item) == 'ilRadioGroupInputGUI') {
					foreach ($item->getOptions() as $op) {
						foreach ($op->getSubItems() as $subItem) {
							$value = $this->origin_properties->getByKey($subItem->getPostVar(), true);
							if ($value) {
								$array[$subItem->getPostVar()] = $value;
							}
						}
					}
				} else {
					foreach ($item->getSubItems() as $subItem) {
						$value = $this->origin_properties->getByKey($subItem->getPostVar(), true);
						if ($value) {
							$array[$subItem->getPostVar()] = $value;
						}
					}
				}
			}
		}

		return $array;
	}


	/**
	 * @return bool
	 */
	protected function fillObjectValues() {
		foreach ($this->getItems() as $item) {
			if (get_class($item) != 'ilFormSectionHeaderGUI') {
				$this->origin_properties->setByKey($item->getPostVar(), $this->getInput($item->getPostVar()));
				if (get_class($item) == 'ilRadioGroupInputGUI') {
					foreach ($item->getOptions() as $op) {
						foreach ($op->getSubItems() as $subItem) {
							$this->origin_properties->setByKey($subItem->getPostVar(), $this->getInput($subItem->getPostVar()));
						}
					}
				} else {
					foreach ($item->getSubItems() as $subItem) {
						$this->origin_properties->setByKey($subItem->getPostVar(), $this->getInput($subItem->getPostVar()));
					}
				}
			}
		}
	}


	/**
	 * @return bool
	 */
	public function fillObject() {
		if (! $this->checkInput()) {
			return false;
		}

		return $this->fillObjectValues();
	}


	/**
	 * @return bool
	 */
	public function saveObject() {
		if (! $this->fillObject()) {
			return false;
		}

		return true;
	}
}