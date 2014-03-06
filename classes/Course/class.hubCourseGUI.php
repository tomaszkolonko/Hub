<?php
require_once('./Services/Utilities/classes/class.ilConfirmationGUI.php');
require_once('class.hubCourse.php');
require_once('class.hubCourseTableGUI.php');

/**
 * GUI-Class hubCourse
 *
 * @author            Fabian Schmid <fs@studer-raimann.ch>
 * @version           $Id:
 *
 */
class hubCourseGUI {

	/**
	 * @var ilTabsGUI
	 */
	protected $tabs_gui;
	/**
	 * @var ilPropertyFormGUI
	 */
	protected $form;
	/**
	 * @var ilToolbarGUI
	 */
	protected $toolbar;
	/**
	 * @var ilCtrl
	 */
	protected $ctrl;
	/**
	 * @var ilTemplate
	 */
	protected $tpl;


	/**
	 * @param $parent_gui
	 */
	public function __construct($parent_gui) {
		global $tpl, $ilCtrl, $ilToolbar, $lng, $ilTabs;
		$this->tpl = $tpl;
		$this->ctrl = $ilCtrl;
		$this->parent = $parent_gui;
		$this->toolbar = $ilToolbar;
		$this->tabs_gui = $ilTabs;
		$this->lng = $lng;
		$this->pl = new ilHubPlugin();
	}


	/**
	 * @return bool
	 */
	public function executeCommand() {
		$cmd = $this->ctrl->getCmd();
		$this->performCommand($cmd);

		return true;
	}


	/**
	 * @param $cmd
	 *
	 * @return mixed|void
	 */
	protected function performCommand($cmd) {
		// TODO Rechteprüfung
		$this->{$cmd}();
	}


	public function index() {
		$tableGui = new hubCourseTableGUI($this, 'index');
		$this->tpl->setContent($tableGui->getHTML());
	}


	public function applyFilter() {
		$tableGui = new hubCourseTableGUI($this, 'index');
		$tableGui->writeFilterToSession();
		$tableGui->resetOffset();
		$this->ctrl->redirect($this, 'index');
	}


	public function resetFilter() {
		$tableGui = new hubCourseTableGUI($this, 'index');
		$tableGui->resetOffset();
		$tableGui->resetFilter();
		$this->ctrl->redirect($this, 'index');
	}
}

?>