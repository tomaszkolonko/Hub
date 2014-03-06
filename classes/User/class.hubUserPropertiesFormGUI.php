<?php
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/OriginProperties/class.hubOriginObjectPropertiesFormGUI.php');

/**
 * Class hubCoursePropertiesFormGUI
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
class hubUserPropertiesFormGUI extends hubOriginObjectPropertiesFormGUI {

	/**
	 * @return string
	 * @description return prefix of object_type like cat, usr, crs, mem
	 */
	protected function getPrefix() {
		return 'usr';
	}


	/**
	 * @description build FormElements
	 */
	protected function initForm() {
		$se = new ilSelectInputGUI($this->pl->txt('usr_prop_syncfield'), 'syncfield');
		$opt = array(
			NULL => $this->pl->txt('usr_prop_syncfield_none'),
			'email' => 'E-Mail',
			'external_account' => 'External Account',
			//			'matriculation' => 'Matriculation',
		);
		$se->setOptions($opt);
		$this->addItem($se);
		//
		$se = new ilSelectInputGUI($this->pl->txt('usr_prop_login_field'), 'login_field');
		$opt = array(
			NULL => $this->pl->txt('usr_prop_login_field_none'),
			'email' => 'E-Mail',
			'external_account' => 'External Account',
			'ext_id' => 'Externe ID',
			'first_and_lastname' => 'vorname.nachname',
		);
		$se->setOptions($opt);
		$this->addItem($se);
		//
		$h = new ilFormSectionHeaderGUI();
		$h->setTitle($this->pl->txt('on_status') . ' NEW');
		$this->addItem($h);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('usr_prop_activate_account'), 'activate_account');
		$this->addItem($cb);
		//
		$h = new ilFormSectionHeaderGUI();
		$h->setTitle($this->pl->txt('on_status') . ' UPDATED');
		$this->addItem($h);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('usr_prop_reactivate_account'), 'reactivate_account');
		$this->addItem($cb);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('usr_prop_update_firstname'), 'update_firstname');
		$this->addItem($cb);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('usr_prop_update_lastname'), 'update_lastname');
		$this->addItem($cb);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('usr_prop_update_email'), 'update_email');
		$this->addItem($cb);
		//
		$cb = new ilCheckboxInputGUI($this->pl->txt('usr_prop_update_login'), 'update_login');
		$this->addItem($cb);
		//
		//
		$h = new ilFormSectionHeaderGUI();
		$h->setTitle($this->pl->txt('on_status') . ' DELETED');
		$this->addItem($h);
		$ro = new ilRadioGroupInputGUI($this->pl->txt('usr_prop_delete_mode'), 'delete');
		$ro->setValue(hubUser::DELETE_MODE_INACTIVE);
		$opt = new ilRadioOption($this->pl->txt('usr_prop_delete_mode_none'), NULL);
		$ro->addOption($opt);
		$opt = new ilRadioOption($this->pl->txt('usr_prop_delete_mode_inactive'), hubUser::DELETE_MODE_INACTIVE);
		$ro->addOption($opt);
		$opt = new ilRadioOption($this->pl->txt('usr_prop_delete_mode_delete'), hubUser::DELETE_MODE_DELETE);
		$ro->addOption($opt);
		$this->addItem($ro);
	}
}