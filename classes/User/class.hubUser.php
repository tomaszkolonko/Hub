<?php
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Hub/classes/class.srModelObjectHubClass.php');

/**
 * Class hubUser
 *
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 *
 * @revision $r$
 */
class hubUser extends srModelObjectHubClass {

	const GENDER_MALE = 'm';
	const GENDER_FEMALE = 'f';
	const ACCOUNT_TYPE_ILIAS = 1;
	const ACCOUNT_TYPE_SHIB = 2;
	const ACCOUNT_TYPE_LDAP = 3;
	const ACCOUNT_TYPE_RADIUS = 4;


	/**
	 * @return bool
	 */
	public static function buildILIASObjects() {
		/**
		 * @var $hubUser hubUser
		 */
		foreach (self::get() as $hubUser) {
			hubCounter::logRunning();
			$existing = NULL;
			$hubUser->loadObjectProperties();
			$existing_usr_id = 0;
			switch ($hubUser->object_properties->getSyncfield()) {
				case 'email':
					$existing_usr_id = self::lookupUsrIdByEmail($hubUser->getEmail());
					break;
				case 'external_account':
					$existing_usr_id = self::lookupUsrIdByExtAccount($hubUser->getExternalAccount());
					break;
			}
			if ($existing_usr_id > 6 AND $hubUser->getHistoryObject()->getStatus() == hubSyncHistory::STATUS_NEW) {
				$history = $hubUser->getHistoryObject();
				$history->setIliasId($existing_usr_id);
				$history->setIliasIdType(self::ILIAS_ID_TYPE_USER);
				$history->update();
			}
			switch ($hubUser->getHistoryObject()->getStatus()) {
				case hubSyncHistory::STATUS_NEW:
					$hubUser->createUser();
					hubCounter::incrementCreated($hubUser->getSrHubOriginId());
					hubOriginNotification::addMessage($hubUser->getSrHubOriginId(), $hubUser->getEmail(), 'User created:');
					break;
				case hubSyncHistory::STATUS_UPDATED:
					$hubUser->updateUser();
					hubCounter::incrementUpdated($hubUser->getSrHubOriginId());
					//					hubOriginNotification::addMessage($hubUser->getSrHubOriginId(), $hubUser->getEmail(), 'User updated:');
					break;
				case hubSyncHistory::STATUS_DELETED:
					$hubUser->deleteUser();
					hubCounter::incrementDeleted($hubUser->getSrHubOriginId());
					hubOriginNotification::addMessage($hubUser->getSrHubOriginId(), $hubUser->getEmail(), 'User deleted:');
					break;
				case hubSyncHistory::STATUS_ALREADY_DELETED:
					hubCounter::incrementDeleted($hubUser->getSrHubOriginId());
					hubOriginNotification::addMessage($hubUser->getSrHubOriginId(), $hubUser->getEmail(), 'User ignored:');
					break;
			}
			$hubUser->getHistoryObject()->updatePickupDate();
			$hubOrigin = hubOrigin::getClassnameForOriginId($hubUser->getSrHubOriginId());
			$hubOrigin::afterObjectModification($hubUser);
		}

		return true;
	}


	public function createUser() {
		$this->ilias_object = new ilObjUser();
		$this->updateLogin();
		$this->updateExternalAuth();
		$this->ilias_object->setTitle($this->getFirstname() . ' ' . $this->getLastname());
		$this->ilias_object->setDescription($this->getEmail());
		$this->ilias_object->setImportId($this->returnImportId());
		$this->ilias_object->create();
		$this->ilias_object->setFirstname($this->getFirstname());
		$this->ilias_object->setLastname($this->getLastname());
		$this->ilias_object->setEmail($this->getEmail());
		if ($this->object_properties->getActivateAccount()) {
			$this->ilias_object->setActive(true);
			$this->ilias_object->setProfileIncomplete(false);
		} else {
			$this->ilias_object->setActive(false);
			$this->ilias_object->setProfileIncomplete(true);
		}
		$this->ilias_object->setInstitution($this->getInstitution());
		$this->ilias_object->setStreet($this->getStreet());
		$this->ilias_object->setCity($this->getCity());
		$this->ilias_object->setZipcode($this->getZipcode());
		$this->ilias_object->setCountry($this->getCountry());
		$this->ilias_object->setPhoneOffice($this->getPhoneOffice());
		$this->ilias_object->setPhoneHome($this->getPhoneHome());
		$this->ilias_object->setPhoneMobile($this->getPhoneMobile());
		$this->ilias_object->setDepartment($this->getDepartment());
		$this->ilias_object->setFax($this->getFax());
		$this->ilias_object->setTimeLimitOwner($this->getTimeLimitOwner());
		$this->ilias_object->setTimeLimitUnlimited($this->getTimeLimitUnlimited());
		$this->ilias_object->setTimeLimitFrom($this->getTimeLimitFrom());
		$this->ilias_object->setTimeLimitUntil($this->getTimeLimitUntil());
		$this->ilias_object->setMatriculation($this->getMatriculation());
		$this->ilias_object->setGender($this->getGender());
		$this->ilias_object->saveAsNew();
		$this->ilias_object->writePrefs();
		$this->assignRoles();
		$history = $this->getHistoryObject();
		$history->setIliasId($this->ilias_object->getId());
		$history->setIliasIdType(self::ILIAS_ID_TYPE_USER);
		$history->update();
	}


	/**
	 * @return bool
	 */
	private function updateLogin() {
		if (! $this->ilias_object) {
			return false;
		}
		switch ($this->object_properties->getLoginField()) {
			case 'email':
				$login = $this->getEmail();
				break;
			case 'external_account':
				$login = $this->getExternalAccount();
				break;
			case 'ext_id':
				$login = $this->getExtId();
				break;
			case 'first_and_lastname':
				$login = self::cleanName($this->getFirstname()) . '.' . self::cleanName($this->getLastname());
				break;
			default:
				$login =
					substr(self::cleanName($this->getFirstname()), 0, 1) . '.' . self::cleanName($this->getLastname());
		}
		$appendix = 2;
		$login_tmp = $login;
		while (self::loginExists($login, $this->ilias_object->getId())) {
			$login = $login_tmp . $appendix;
			$appendix ++;
		}
		//$this->ilias_object->updateLogin($login); has restriction --> direct Method
		$this->ilias_object->setLogin($login);
		$this->db->manipulateF('UPDATE usr_data SET login = %s WHERE usr_id = %s', array(
			'text',
			'integer'
		), array(
			$this->ilias_object->getLogin(),
			$this->ilias_object->getId()
		));

		return true;
	}


	public function updateUser() {
		if ($this->object_properties->getUpdateLogin() OR $this->object_properties->getUpdateFirstname()
			OR $this->object_properties->getUpdateLastname() OR $this->object_properties->getUpdateEmail()
			OR $this->object_properties->getReactivateAccount()
		) {
			$this->ilias_object = new ilObjUser($this->getHistoryObject()->getIliasId());
			$this->ilias_object->setImportId($this->returnImportId());
			$this->ilias_object->setTitle($this->getFirstname() . ' ' . $this->getLastname());
			$this->ilias_object->setDescription($this->getEmail());
			if ($this->object_properties->getUpdateLogin()) {
				$this->updateLogin();
			}
			if ($this->object_properties->getUpdateFirstname()) {
				$this->ilias_object->setFirstname($this->getFirstname());
			}
			if ($this->object_properties->getUpdateLastname()) {
				$this->ilias_object->setLastname($this->getLastname());
			}
			if ($this->object_properties->getUpdateEmail()) {
				$this->ilias_object->setEmail($this->getEmail());
			}
			//
			$this->ilias_object->setInstitution($this->getInstitution());
			$this->ilias_object->setStreet($this->getStreet());
			$this->ilias_object->setCity($this->getCity());
			$this->ilias_object->setZipcode($this->getZipcode());
			$this->ilias_object->setCountry($this->getCountry());
			$this->ilias_object->setSelectedCountry($this->getCountry());
			$this->ilias_object->setPhoneOffice($this->getPhoneOffice());
			$this->ilias_object->setPhoneHome($this->getPhoneHome());
			$this->ilias_object->setPhoneMobile($this->getPhoneMobile());
			$this->ilias_object->setDepartment($this->getDepartment());
			$this->ilias_object->setFax($this->getFax());
			$this->ilias_object->setTimeLimitOwner($this->getTimeLimitOwner());
			$this->ilias_object->setTimeLimitUnlimited($this->getTimeLimitUnlimited());
			$this->ilias_object->setTimeLimitFrom($this->getTimeLimitFrom());
			$this->ilias_object->setTimeLimitUntil($this->getTimeLimitUntil());
			$this->ilias_object->setMatriculation($this->getMatriculation());
			$this->ilias_object->setGender($this->getGender());
			if ($this->object_properties->getReactivateAccount()) {
				$this->ilias_object->setActive(true);
			}
			$this->updateExternalAuth();
			$this->ilias_object->update();
			$this->assignRoles();
		}
	}


	public function deleteUser() {
		if ($this->object_properties->getDelete()) {
			$this->ilias_object = new ilObjUser($this->getHistoryObject()->getIliasId());
			switch ($this->object_properties->getDelete()) {
				case self::DELETE_MODE_INACTIVE:
					$this->ilias_object->setActive(false);
					$this->ilias_object->update();
					break;
				case self::DELETE_MODE_DELETE:
					$this->ilias_object->delete();
					$hist = $this->getHistoryObject();
					$hist->setAlreadyDeleted(true);
					break;
			}
		}
	}


	/**
	 * @return bool
	 * @description Assign roles stored in field ilias_roles to ilias user object
	 */
	private function assignRoles() {
		if (! $this->ilias_object) {
			return false;
		}
		/**
		 * @var  $rbacadmin ilRbacAdmin
		 */
		global $rbacadmin;
		$user_id = $this->ilias_object->getId();
		if ($user_id AND count($this->ilias_roles)) {
			foreach ($this->ilias_roles as $role_id) {
				$rbacadmin->assignUser($role_id, $user_id);
			}
		}

		return true;
	}


	/**
	 * @return bool
	 */
	private function updateExternalAuth() {
		if (! $this->ilias_object) {
			return false;
		}
		$auth_mode = '';
		switch ($this->getAccountType()) {
			case self::ACCOUNT_TYPE_ILIAS:
				$auth_mode = 'local';
				break;
			case self::ACCOUNT_TYPE_SHIB:
				$auth_mode = 'shibboleth';
				break;
			case self::ACCOUNT_TYPE_LDAP:
				$auth_mode = 'ldap';
				break;
			case self::ACCOUNT_TYPE_RADIUS:
				$auth_mode = 'radius';
				break;
		}
		$this->ilias_object->setAuthMode($auth_mode);
		$this->ilias_object->setExternalAccount($this->getExternalAccount());

		return true;
	}


	//
	// Helper
	//
	/**
	 * @param $login
	 * @param $usr_id
	 *
	 * @return bool
	 */
	private static function loginExists($login, $usr_id) {
		global $ilDB;
		/**
		 * @var $ilDB ilDB
		 */
		$q = 'SELECT usr_id FROM usr_data WHERE login = ' . $ilDB->quote($login, 'text');
		$q .= ' AND usr_id != ' . $ilDB->quote($usr_id, 'integer');

		return (bool)$ilDB->numRows($ilDB->query($q));
	}


	/**
	 * @param $fieldname
	 * @param $value
	 *
	 * @return bool
	 */
	public static function lookupUsrIdByField($fieldname, $value) {
		global $ilDB;
		$q = 'SELECT usr_id FROM usr_data WHERE ' . $fieldname . ' LIKE ' . $ilDB->quote($value, 'text');
		$res = $ilDB->query($q);
		$existing = $ilDB->fetchObject($res);

		return $existing->usr_id ? $existing->usr_id : false;
	}


	/**
	 * @param $email
	 *
	 * @return bool
	 */
	public static function lookupUsrIdByEmail($email) {
		return self::lookupUsrIdByField('email', $email);
	}


	/**
	 * @param $external_account
	 *
	 * @return bool
	 */
	public static function lookupUsrIdByExtAccount($external_account) {
		return self::lookupUsrIdByField('ext_account', $external_account);
	}


	//
	// Fields
	//
	/**
	 * @var ilObjUser
	 */
	protected $ilias_object;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           4
	 */
	protected $sr_hub_origin_id;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $passwd;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $firstname;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $lastname;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $title;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           1
	 */
	protected $gender;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $email;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $institution;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $street;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $city;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $zipcode;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $country;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $phone_office;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $department;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $phone_home;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $phone_mobile;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $fax;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $time_limit_owner;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $time_limit_unlimited;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $time_limit_from;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $time_limit_until;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $matriculation;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        clob
	 */
	protected $image;
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           1
	 */
	protected $account_type = self::ACCOUNT_TYPE_ILIAS;
	/**
	 * @var string
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $external_account;
	/**
	 * @var array
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           256
	 */
	protected $ilias_roles = array();


	/**
	 * @param hubOrigin $origin
	 */
	public function updateInto(hubOrigin $origin) {
		$temp = $this->ilias_roles;
		$this->ilias_roles = implode(',', $this->ilias_roles);
		parent::updateInto($origin);
		$this->ilias_roles = $temp;
	}


	public function read() {
		parent::read();
		$this->ilias_roles = @explode(',', $this->ilias_roles);
	}


	/**
	 * @param $role_id
	 */
	public function addRole($role_id) {
		$this->ilias_roles[] = $role_id;
		$this->ilias_roles = array_unique($this->ilias_roles);
	}


	private function clearRoles() {
		//$this->ilias_roles = array();
	}


	/**
	 * @return string
	 */
	static function returnDbTableName() {
		return 'sr_hub_user';
	}


	/**
	 * @param string $city
	 */
	public function setCity($city) {
		$this->city = $city;
	}


	/**
	 * @return string
	 */
	public function getCity() {
		return $this->city;
	}


	/**
	 * @param string $country
	 */
	public function setCountry($country) {
		$this->country = $country;
	}


	/**
	 * @return string
	 */
	public function getCountry() {
		return $this->country;
	}


	/**
	 * @param string $department
	 */
	public function setDepartment($department) {
		$this->department = $department;
	}


	/**
	 * @return string
	 */
	public function getDepartment() {
		return $this->department;
	}


	/**
	 * @param string $email
	 */
	public function setEmail($email) {
		$this->email = $email;
	}


	/**
	 * @return string
	 */
	public function getEmail() {
		return $this->email;
	}


	/**
	 * @param string $fax
	 */
	public function setFax($fax) {
		$this->fax = $fax;
	}


	/**
	 * @return string
	 */
	public function getFax() {
		return $this->fax;
	}


	/**
	 * @param string $firstname
	 */
	public function setFirstname($firstname) {
		$this->firstname = $firstname;
	}


	/**
	 * @return string
	 */
	public function getFirstname() {
		return $this->firstname;
	}


	/**
	 * @param mixed $gender
	 */
	public function setGender($gender) {
		$this->gender = $gender;
	}


	/**
	 * @return mixed
	 */
	public function getGender() {
		return $this->gender;
	}


	/**
	 * @param string $institution
	 */
	public function setInstitution($institution) {
		$this->institution = $institution;
	}


	/**
	 * @return string
	 */
	public function getInstitution() {
		return $this->institution;
	}


	/**
	 * @param string $lastname
	 */
	public function setLastname($lastname) {
		$this->lastname = $lastname;
	}


	/**
	 * @return string
	 */
	public function getLastname() {
		return $this->lastname;
	}


	/**
	 * @param string $matriculation
	 */
	public function setMatriculation($matriculation) {
		$this->matriculation = $matriculation;
	}


	/**
	 * @return string
	 */
	public function getMatriculation() {
		return $this->matriculation;
	}


	/**
	 * @param string $passwd
	 */
	public function setPasswd($passwd) {
		$this->passwd = $passwd;
	}


	/**
	 * @return string
	 */
	public function getPasswd() {
		return $this->passwd;
	}


	/**
	 * @param string $phone_home
	 */
	public function setPhoneHome($phone_home) {
		$this->phone_home = $phone_home;
	}


	/**
	 * @return string
	 */
	public function getPhoneHome() {
		return $this->phone_home;
	}


	/**
	 * @param string $phone_mobile
	 */
	public function setPhoneMobile($phone_mobile) {
		$this->phone_mobile = $phone_mobile;
	}


	/**
	 * @return string
	 */
	public function getPhoneMobile() {
		return $this->phone_mobile;
	}


	/**
	 * @param string $phone_office
	 */
	public function setPhoneOffice($phone_office) {
		$this->phone_office = $phone_office;
	}


	/**
	 * @return string
	 */
	public function getPhoneOffice() {
		return $this->phone_office;
	}


	/**
	 * @param array $primary_fields
	 */
	public static function setPrimaryFields($primary_fields) {
		self::$primary_fields = $primary_fields;
	}


	/**
	 * @return array
	 */
	public static function getPrimaryFields() {
		return self::$primary_fields;
	}


	/**
	 * @param mixed $sr_hub_origin_id
	 */
	public function setSrHubOriginId($sr_hub_origin_id) {
		$this->sr_hub_origin_id = $sr_hub_origin_id;
	}


	/**
	 * @return mixed
	 */
	public function getSrHubOriginId() {
		return $this->sr_hub_origin_id;
	}


	/**
	 * @param string $street
	 */
	public function setStreet($street) {
		$this->street = $street;
	}


	/**
	 * @return string
	 */
	public function getStreet() {
		return $this->street;
	}


	/**
	 * @param string $time_limit_from
	 */
	public function setTimeLimitFrom($time_limit_from) {
		$this->time_limit_from = $time_limit_from;
	}


	/**
	 * @return string
	 */
	public function getTimeLimitFrom() {
		return $this->time_limit_from;
	}


	/**
	 * @param string $time_limit_owner
	 */
	public function setTimeLimitOwner($time_limit_owner) {
		$this->time_limit_owner = $time_limit_owner;
	}


	/**
	 * @return string
	 */
	public function getTimeLimitOwner() {
		return $this->time_limit_owner;
	}


	/**
	 * @param string $time_limit_unlimited
	 */
	public function setTimeLimitUnlimited($time_limit_unlimited) {
		$this->time_limit_unlimited = $time_limit_unlimited;
	}


	/**
	 * @return string
	 */
	public function getTimeLimitUnlimited() {
		return $this->time_limit_unlimited;
	}


	/**
	 * @param string $time_limit_until
	 */
	public function setTimeLimitUntil($time_limit_until) {
		$this->time_limit_until = $time_limit_until;
	}


	/**
	 * @return string
	 */
	public function getTimeLimitUntil() {
		return $this->time_limit_until;
	}


	/**
	 * @param string $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}


	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}


	/**
	 * @param string $zipcode
	 */
	public function setZipcode($zipcode) {
		$this->zipcode = $zipcode;
	}


	/**
	 * @return string
	 */
	public function getZipcode() {
		return $this->zipcode;
	}


	/**
	 * @param string $image
	 */
	public function setImage($image) {
		$this->image = $image;
	}


	/**
	 * @return string
	 */
	public function getImage() {
		return $this->image;
	}


	/**
	 * @param int $account_type
	 */
	public function setAccountType($account_type) {
		$this->account_type = $account_type;
	}


	/**
	 * @return int
	 */
	public function getAccountType() {
		return $this->account_type;
	}


	/**
	 * @param string $external_account
	 */
	public function setExternalAccount($external_account) {
		$this->external_account = $external_account;
	}


	/**
	 * @return string
	 */
	public function getExternalAccount() {
		return $this->external_account;
	}


	/**
	 * @param array $ilias_roles
	 */
	public function setIliasRoles($ilias_roles) {
		$this->ilias_roles = $ilias_roles;
	}


	/**
	 * @return array
	 */
	public function getIliasRoles() {
		return $this->ilias_roles;
	}


	//
	// Helper
	//
	/**
	 * @param $name
	 *
	 * @return mixed
	 */
	protected static function cleanName($name) {
		$upas = array(
			'ä' => 'ae',
			'ü' => 'ue',
			'ö' => 'oe',
			'Ä' => 'Ae',
			'Ü' => 'Ue',
			'Ö' => 'Oe',
			'é' => 'e',
			'è' => 'e',
			'ê' => 'e',
			'Á' => 'A',
			'\'' => '',
			' ' => '',
			'-' => '',
			'.' => '',
		);

		return strtolower(strtr($name, $upas));
	}
}

?>