<?php
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/OriginProperties/class.hubOriginObjectPropertiesFields.php');

/**
 * Class hubCategoryFields
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 *
 * @version 1.1.02
 */
class hubCategoryFields extends hubOriginObjectPropertiesFields {

	const BASE_NODE_ILIAS = 'base_node_ilias';
	const BASE_NODE_EXTERNAL = 'base_node_external';
	const SYNCFIELD = 'syncfield';
	const CREATE_ICON = 'create_icon';
	const MOVE = 'move';
	const UPDATE_TITLE = 'update_title';
	const UPDATE_DESCRIPTION = 'update_description';
	const UPDATE_ICON = 'update_icon';
	const DELETE = 'delete';
	const DELETED_ICON = 'deleted_icon';
	const ARCHIVE_NODE = 'archive_node';
}

?>
