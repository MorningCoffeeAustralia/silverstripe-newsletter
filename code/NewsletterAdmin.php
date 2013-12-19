<?php

/**
 * Newsletter administration section
 *
 * @package newsletter
 */

class NewsletterAdmin extends LeftAndMain {
	
	public static $subitem_class = 'Member';
	
	/** 
	 * @var which will be used to seperator "send items" into 2 groups, e.g. "most recent number 5", "older". 
	 */
	public static $most_recent_seperator = 5; // an int which will be used to seperator "send items" into 2 groups, e.g. "most recent number 5", "older".
	
	/** 
	 * @var array Array of template paths to check 
	 */
	public static $template_paths = null; //could be customised in _config 

	public static $allowed_actions = array(
		'addarticle',
		'adddraft',
		'addgroup',
		'addtype',
		'autocomplete',
		'displayfilefield',
		'getformcontent',
		'getsentstatusreport',
		'getsitetree',
		'memberblacklisttoggle',
		'newmember',
		'preview',
		'remove',
		'removebouncedmember',
		'removenewsletter',
		'save',
		'savemember',
		'savenewsletter',
		'sendnewsletter',
		'showdrafts',
		'showmailtype',
		'shownewsletter',
		'showrecipients',
		'showsent',
		'showarticle',
		'parentchange',
		'orderchange',
		'MailingListEditForm',
		'TypeEditForm',
		'UploadForm',
		'NewsletterEditForm',
	);

	public static $url_segment = 'newsletter';
	public static $url_rule    = '/$Action/$ID/$OtherID';
	public static $menu_title  = 'Newsletter';

	protected $currentID         = null;
	protected $request           = null;
	public    $newsletter        = null;
	public    $newsletterArticle = null;
	public    $newsletterType    = null;

	public function init() {
		// In LeftAndMain::init() the current theme is unset.
		// we need to restore the current theme here for make the dropdown of template list.
		$theme = SSViewer::current_theme();
		
		parent::init();
		
		if(isset($theme) && $theme){
			SSViewer::set_theme($theme);
		}
		
		Requirements::javascript(MCE_ROOT . 'tiny_mce_src.js');
		Requirements::javascript(SAPPHIRE_DIR . '/javascript/tiny_mce_improvements.js');

		//TODO what is going on here? where did that hover.js go? can't find it. 
		//TODO We need to reimplement a hover.js?
		Requirements::javascript(CMS_DIR . '/javascript/hover.js');
		Requirements::javascript(THIRDPARTY_DIR . '/scriptaculous/controls.js');

		Requirements::javascript(CMS_DIR . '/javascript/LeftAndMain_left.js');
		Requirements::javascript(CMS_DIR . '/javascript/LeftAndMain_right.js');
		Requirements::javascript(CMS_DIR . '/javascript/CMSMain_left.js');
		
		Requirements::javascript(CMS_DIR . '/javascript/SecurityAdmin.js');

		Requirements::javascript(NEWSLETTER_DIR . '/javascript/NewsletterAdmin_left.js');
		Requirements::javascript(NEWSLETTER_DIR . '/javascript/NewsletterAdmin_right.js');
		Requirements::javascript(NEWSLETTER_DIR . '/javascript/ProgressBar.js');

		// We don't want this showing up in every ajax-response, it should always be present in a CMS-environment
		if(!Director::is_ajax()) {
			Requirements::javascript(MCE_ROOT . 'tiny_mce_src.js');
			HtmlEditorConfig::get('cms')->setOption('ContentCSS', project() . '/css/editor.css');
			HtmlEditorConfig::get('cms')->setOption('Lang', i18n::get_tinymce_lang());
		}

		// Always block the HtmlEditorField.js otherwise it will be sent with an ajax request
		Requirements::block(SAPPHIRE_DIR . '/javascript/HtmlEditorField.js');

		Requirements::css(NEWSLETTER_DIR . '/css/NewsletterAdmin.css');
	}

	public function remove() {
		$ids = explode( ',', $_REQUEST['csvIDs'] );

		$count = 0;
		foreach( $ids as $id ) {
			
			$record = null;
			if( preg_match( '/^mailtype_(\d+)$/', $id, $matches ) )
				$record = DataObject::get_by_id( 'NewsletterType', $matches[1] );
			else if( preg_match( '/^[a-z]+_\d+_(\d+)$/', $id, $matches ) )
				$record = DataObject::get_by_id( 'Newsletter', $matches[1] );
			else if( preg_match( '/^article_(\d+)$/', $id, $matches ) )
				$record = DataObject::get_by_id( 'NewsletterArticle', $matches[1] );

			if( $record ) {
				$record->delete();
			}

			FormResponse::add("removeTreeNodeByIdx(\$('sitetree'), '$id' );");
			// Don't allow a deleted draft to be edited
			FormResponse::add("$('Form_EditForm').closeIfSetTo('$matches[1]');");
			$count++;
		}

		FormResponse::status_message('Deleted '.$count.' items','good');

		return FormResponse::respond();
	}

	public function getformcontent(){
		$this->setCurrentPageIDFromRequest();

		$request = $this->getCachedRequest();
		if (isset($request['otherID']) && $request['otherID'] && is_numeric($request['otherID'])) {
			Session::set('currentOtherID', intval($request['otherID']));
		}

		SSViewer::setOption('rewriteHashlinks', false);

		$result = $this->renderWith($this->class . '_right');

		return $this->getLastFormIn($result);
	}

	/**
	 * Top level call from ajax
	 * Called when a mailing list is clicked on the left menu
	 */
	public function showrecipients($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		return $this->showWithEditForm($this->getMailingListEditForm());
	}

	/**
	* Top level call from ajax when click on the left manu
	* Second level call when create a draft
	* Called when a draft or sent newsletter is clicked on the left menu and when a new one is added
	*/
	public function shownewsletter($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		return $this->showWithEditForm($this->getNewsletterEditForm());
	}

	/**
	 * Preview a {@link Newsletter} draft.
	 *
	 * @param SS_HTTPRequest $request Request parameters
	 */
	public function preview($request) {
		$this->setCachedRequest($request);
		$this->setNewsletterFromRequest();

		$templateName = $this->newsletter->Parent()->Template ?: 'GenericEmail';

		// Block stylesheets and JS that are not required (email templates should have inline CSS/JS)
		Requirements::clear();

		$email = new NewsletterEmail($this->newsletter);
		$email->populateTemplate();

		return HTTP::absoluteURLs($email->getData()->renderWith($templateName));
	}

	/**
	 * Top level call from ajax
	 * Called when a newsletter type is clicked on the left menu
	 */
	public function showmailtype($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();
		
		return $this->showWithEditForm($this->getNewsletterTypeEditForm());
	}

	/**
	* Top level call from ajax
	* Called when a 'Drafts' folder is clicked on the left menu
	*/
	public function showdrafts($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		return $this->ShowNewsletterFolder('Draft');
	}

	/**
	* Top level call from ajax
	* Called when a 'Sent Items' folder is clicked on the left menu
	*/
	public function showsent($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		return $this->ShowNewsletterFolder('Sent');
	}
	
	public function showarticle($params) {
		$this->setCachedRequest($params);

		$form = $this->getArticleEditForm();

		return $this->showWithEditForm($form);
	}

	/**
	* Shows either the 'Sent' or 'Drafts' folder using the NewsletterList template
	* Didn't see anywhere it is called from top level ajax call or from templete,
	* it is only called internally from showdrafts and showsent.
	*/
	public function ShowNewsletterFolder($type) {
		$this->setNewsletterTypeFromRequest();

		$draftList = new NewsletterList($type, $this->newsletterType, $type);
		return $draftList->renderWith("NewsletterList");
	}

	/**
	 * This function is called only internally, so make sure that $params is not a SS_HTTPRequest from caller.
	 */
	private function showWithEditForm($editForm) {
		$this->setCurrentPageIDFromRequest();

		$request = $this->getCachedRequest();
		if (isset($request['OtherID']) && $request['OtherID'] && is_numeric($request['OtherID'])) {
			Session::set('currentMember', intval($request['OtherID']));
		}

		if (Director::is_ajax()) {
			SSViewer::setOption('rewriteHashlinks', false);
			return $editForm->formHtmlContent();
		} else {
			return array('EditForm' => $editForm);
		}
	}

	public function getEditForm() {
		$form = $this->getNewsletterTypeEditForm();
		$form->disableDefaultAction();
		return $form;
	}

	/**
	 * Get the EditForm
	 */
	public function EditForm() {
		$this->setCurrentPageIDFromRequest();

		// Include JavaScript to ensure HtmlEditorField works.
		HtmlEditorField::include_js();

		if ($this->currentID) {
			if ((isset($this->request['Type']) && $this->request['Type'] == 'Newsletter')
				|| isset($this->request['action_savenewsletter'])
			) {
				$form = $this->NewsletterEditForm();
			} elseif (isset($this->request['Type']) && $this->request['Type'] == 'Article') {
				$form = $this->ArticleEditForm();
			} else {
				// If a mailing list member is being added to a group, then call the Recipient form
				if ((isset($this->request['fieldName']) && $this->request['fieldName'] == 'Recipients')
					|| (!empty($this->request['MemberSearch']))
				) {
					$form = $this->MailingListEditForm();
				} else {
					$form = $this->TypeEditForm();
				}
			}
			if ($form) {
				$form->disableDefaultAction();
			}
			return $form;
		}
	}

	public function NewsletterEditForm() {
		$this->setCurrentPageIDFromRequest();

		return $this->getNewsletterEditForm();
	}

	public function ArticleEditForm() {
		return $this->getArticleEditForm();
	}

	public function TypeEditForm() {
		$this->setCurrentPageIDFromRequest();

		return $this->getNewsletterTypeEditForm();
	}
	public function MailingListEditForm() {
		return $this->getMailingListEditForm();
	}

	public function getNewsletterTypeEditForm() {
		$form = null;
		$this->setNewsletterTypeFromRequest();

		$fields = $this->newsletterType->getCMSFields();
		$fields->push($idField = new HiddenField("ID"));
		$idField->setValue($this->currentID);
		$fields->push(
			new HiddenField("executeForm", "", "TypeEditForm")
		);

		$actions = new FieldSet(
			new FormAction('save', _t('NewsletterAdmin.SAVE', 'Save'))
		);

		$form = new Form($this, "TypeEditForm", $fields, $actions);
		$form->loadDataFrom($this->newsletterType);
		// This saves us from having to change all the JS in response to renaming this form to TypeEditForm
		$form->setHTMLID('Form_EditForm');
		$this->extend('updateEditForm', $form);

		return $form;
	}

	public function getMailingListEditForm() {
		$this->setNewsletterTypeFromRequest();

		$group = $this->newsletterType->GroupID
			? DataObject::get_one("Group", "\"ID\" = {$this->newsletterType->GroupID}")
			: null;

		if ($group) {
			$fields = new FieldSet(
				new TabSet("Root",
					new Tab(_t('NewsletterAdmin.RECIPIENTS', 'Recipients'),
						$recipients = new NewsletterAdminMemberTableField(
							$this,
							"Recipients",
							$group
							)
					),
					new Tab(_t('NewsletterAdmin.IMPORT', 'Import'),
						$importField = new RecipientImportField("ImportFile",_t('NewsletterAdmin.IMPORTFROM', 'Import from file'), $group)
					),
					new Tab(_t('NewsletterAdmin.UNSUBSCRIBERS', 'Unsubscribers'),
					$unsubscribedList = new UnsubscribedList("Unsubscribed", $this->newsletterType)
					),
					new Tab(_t('NewsletterAdmin.BOUNCED','Bounced'), $bouncedList = new BouncedList("Bounced", $this->newsletterType)
					)
				)
			);

			$recipients->setController($this);
			$importField->setController($this);
		  	$unsubscribedList->setController($this);
			$bouncedList->setController($this);

			$importField->setTypeID($this->currentID);

			$fields->push($idField = new HiddenField("ID"));
			$fields->push(new HiddenField("executeForm", "", "MailingListEditForm" ));
			$idField->setValue($this->currentID);
			// Save button is not used in Mailing List section
			$actions = new FieldSet(new HiddenField("save"));

			$form = new Form($this, "MailingListEditForm", $fields, $actions);
			$form->loadDataFrom(array(
				'Title' => $this->newsletterType->Title,
				'FromEmail' => $this->newsletterType->FromEmail
			));
			// This saves us from having to change all the JS in response to renaming this form to MailingListEditForm
			$form->setHTMLID('Form_EditForm');
			$this->extend('updateEditForm', $form);
		} else {
			$fields = new FieldSet(
				new LiteralField('GroupWarning', _t('NewsletterAdmin.NO_GROUP', 'No mailing group selected'))
			);
			$form = new Form($this, "MailingListEditForm", $fields, new FieldSet());
		}

		return $form;
	}

	public function getArticleEditForm() {
		$this->setNewsletterArticleFromRequest();

		$fields = $this->newsletterArticle->getCMSFields();

		// add some extra fields used by LeftAndMain
		$fields->removeByName('Image');
		$fields->addFieldToTab('Root.Main', new HiddenField('ID', 'ID', $this->currentID));
		$fields->addFieldToTab('Root.Main', new HiddenField('Type', 'Type', 'Article'));
		$fields->addFieldToTab('Root.Main', new LiteralField('ImageUpload', '<iframe name="Image_iframe" src="admin/newsletterarticle/NewsletterArticle/' . $this->currentID . '/EditForm/field/Image/iframe" style="height: 152px; width: 100%; border: none;"></iframe>'));
		
		$actions = new FieldSet(new FormAction('save', _t('NewsletterAdmin.SAVE', 'Save')));

		// keeping form name as EditForm
		// this hooks into the NewsletterAdmin_right.js to tigger saves
		$form = new Form($this, "EditForm", $fields, $actions);
		$form->loadDataFrom($this->newsletterArticle);

		$this->newsletter = $this->newsletterArticle->Newsletter();
		if(!$this->newsletter->isDraft()) {
			$readonlyFields = $form->Fields()->makeReadonly();
			$form->setFields($readonlyFields);
		}

		return $form;
	}

	/**
	 * Removes a bounced member from the mailing list
	 * top level call from front-ajax
	 * @return String
	 */
	function removebouncedmember($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		// First remove the Bounce entry
		$bounceObject = DataObject::get_by_id('Email_BounceRecord', $this->currentID);
		if($bounceObject) {
			// Remove this bounce record
			$bounceObject->delete();

			$memberObject = DataObject::get_by_id('Member', $bounceObject->MemberID);
			$groupID = $this->request['GroupID'];
			if(is_numeric($groupID) && $memberObject) {
				// Remove the member from the mailing list
				$memberObject->Groups()->remove($groupID);
			} else {
				user_error("NewsletterAdmin::removebouncedmember: Bad parameters: Group=$groupID, Member=".$bounceObject->MemberID, E_USER_ERROR);
			}
			FormResponse::status_message($memberObject->Email.' '._t('NewsletterAdmin.REMOVEDSUCCESS', 'was removed from the mailing list'), 'good');
			FormResponse::add("$('Form_EditForm').getPageFromServer($('Form_EditForm_ID').value, 'recipients');");
			return FormResponse::respond();
		}
	}

	/**
	 * Reloads the "Sent Status Report" tab via ajax
	 * top level call from ajax
	 */
	function getsentstatusreport($params) {
		$this->setCachedRequest($params);
		
		if(Director::is_ajax()) {
			$this->setNewsletterFromRequest();
			return $this->newsletter->renderWith("Newsletter_SentStatusReport");
		}
	}
	
	/**
	 * looked-up the email template_paths. 
	 * if not set, will look up both theme folder and project folder
	 * in both cases, email folder exsits or Email folder exists
	 * return an array containing all folders pointing to the bunch of email templates
	 *
	 * @return array
	 */
	public static function template_paths() {
		if (self::$template_paths === null) {
			self::$template_paths = array();

			if (ClassInfo::exists('Subsite')) {
				self::set_multi_site_template_paths();
			}
			else {
				self::set_single_site_template_paths();
			}
		}
		else if (is_string(self::$template_paths)) {
			self::$template_paths = array(self::$template_paths);
		}

		return self::$template_paths;
	}

	/**
	 * Since an instance with subsites enabled could have many active themes and many active projects
	 * get all module directories and all themes
	 */
	protected static function set_multi_site_template_paths() {
		$paths = array();

		// Find the path segments for all modules
		$classes = ClassInfo::subclassesFor('DataObject');
		array_shift($classes);

		$modules = array();
		foreach ($classes as $class) {
			$model = new ModelViewer_Model($class);
			$modules[$model->Module] = $model->Module;
		}

		// Add the module paths to the path list
		foreach ($modules as $module) {
			$paths[] = "$module/templates/email";
			$paths[] = "$module/templates/Email";
		}

		// Get all themes and add to path list
		// If non-theme directories exist, they are filtered out before adding to self::$template_paths
		if ($handle = opendir(BASE_PATH.'/'.THEMES_DIR)) {
			while (false !== ($theme = readdir($handle))) {
				if ($theme != '.' && $theme != '..') {
					$paths[] = THEMES_DIR."/$theme/templates/email";
					$paths[] = THEMES_DIR."/$theme/templates/Email";
				}
			}
		}

		// For each path that is a valid directory, add to the self::$template_paths array
		foreach ($paths as $path) {
			if (is_dir("../$path")) {
				self::$template_paths[] = $path;
			}
		}
	}

	/**
	 * In a single site instance we can rely on just the active project and theme
	 */
	protected static function set_single_site_template_paths() {
		if (file_exists("../".THEMES_DIR."/".SSViewer::current_theme()."/templates/email")) {
			self::$template_paths[] = THEMES_DIR."/".SSViewer::current_theme()."/templates/email";
		}

		if (file_exists("../".THEMES_DIR."/".SSViewer::current_theme()."/templates/Email")) {
			self::$template_paths[] = THEMES_DIR."/".SSViewer::current_theme()."/templates/Email";
		}

		if (file_exists("../".project() . '/templates/email')) {
			self::$template_paths[] = project() . '/templates/email';
		}

		if (file_exists("../".project() . '/templates/Email')) {
			self::$template_paths[] = project() . '/templates/Email';
		}
	}

	/**
	 * return array containing all possible email templates file name 
	 * under the folders of both theme and project specific folder.
	 *
	 * @return array
	 */
	public function templateSource() {
		$paths = self::template_paths();
		$templates = array('' => _t('TemplateList.NONE', 'None'));

		if (isset($paths) && is_array($paths)) {
			$absPath = Director::baseFolder();
			if ($absPath{strlen($absPath)-1} != "/") {
				$absPath .= '/';
			}
				
			foreach ($paths as $path) {
				$path = $absPath.$path;
				if (is_dir($path)) {
					$templateDir = opendir($path);

					// read all files in the directory
					while (($templateFile = readdir($templateDir)) !== false) {
						// *.ss files are templates
						if (preg_match('/(.*)\.ss$/', $templateFile, $match)) {
							$templates[$match[1]] = preg_replace('/_?([A-Z])/', " $1", $match[1]);
						}
					}
				}
			}
		}
		
		return $templates;
	}

	public function getNewsletterEditForm() {
		$this->setNewsletterFromRequest();

		$fields  = $this->newsletter->getCMSFields($this);
		$actions = $this->newsletter->getCMSActions();

		$fields->push($idField = new HiddenField("ID"));
		$idField->setValue($this->currentID);
		$fields->push($ParentidField = new HiddenField("ParentID"));
		$ParentidField->setValue($this->newsletter->ParentID);
		$fields->push($typeField = new HiddenField("Type"));
		$typeField->setValue('Newsletter');

		$form = new Form($this, "NewsletterEditForm", $fields, $actions);
		$form->loadDataFrom($this->newsletter);
		// This saves us from having to change all the JS in response to renaming this form to NewsletterEditForm
		$form->setHTMLID('Form_EditForm');

		if(!$this->newsletter->isDraft()) {
			$readonlyFields = $form->Fields()->makeReadonly();
			$form->setFields($readonlyFields);
		}

		$this->extend('updateEditForm', $form);
		return $form;
	}

	public function SendProgressBar() {
		$progressBar = new ProgressBar('SendProgressBar', _t('NewsletterAdmin.SENDING', 'Sending emails...'));
		return $progressBar->FieldHolder();
	}

	/**
	 * Sends a newsletter given by the url 
	 */
	public function sendnewsletter() {
		$this->setCachedRequest();
		$this->setCurrentPageIDFromRequest();

		if (!$this->currentID) {
			FormResponse::status_message(_t('NewsletterAdmin.NONLSPECIFIED', 'No newsletter specified'),'bad');
			return FormResponse::respond();
		}

		$this->setNewsletterFromRequest();
		$nlType = $this->newsletter->getNewsletterType();

		$e = new NewsletterEmail($this->newsletter, $nlType);
		$e->Subject = $subject = $this->newsletter->Subject;
		$e->From = $from = ($nlType && $nlType->FromEmail ? $nlType->FromEmail : Email::getAdminEmail());
		$e->setTemplate($nlType->Template);

		$messageID = base64_encode($this->newsletter->ID . '_' . date('d-m-Y H:i:s'));

		switch($this->request['SendType']) {
			case "Test":
				if($this->request['TestEmail']) {
					self::sendToAddress($e, $this->request['TestEmail'], $messageID);
					FormResponse::status_message(_t('NewsletterAdmin.SENTTESTTO','Sent test to ') . $this->request['TestEmail'], 'good');
				} else {
					FormResponse::status_message(_t('NewsletterAdmin.PLEASEENTERMAIL','Please enter an email address'), 'bad');
				}
				break;
			case "List":
				// Send to the entire mailing list.
				$groupID = $nlType->GroupID;
				$recipients = DataObject::get('Member', "\"GroupID\"='$groupID'", null, "INNER JOIN \"Group_Members\" ON \"MemberID\"=\"Member\".\"ID\"");
				$this->extend('updateRecipients', $this->request['SendType'], $recipients);
				echo self::sendToList($subject, $from, $this->newsletter, $nlType, $recipients, $messageID);
				break;
			case "Unsent":
				// Send to only those who have not already been sent this newsletter.
				$recipients = $this->newsletter->UnsentSubscribers();
				$this->extend('updateRecipients', $this->request['SendType'], $recipients);
				echo self::sendToList($subject, $from, $this->newsletter, $nlType, $recipients, $messageID);
				break;
		}

		return FormResponse::respond();
	}


	public static function sendToAddress($email, $address, $messageID = null) {
		$email->To = $address;
		$email->send();
	}

	public static function sendToList($subject, $from, $newsletter, $nlType, $recipients, $messageID = null) {
		$emailProcess = new NewsletterEmailProcess($subject, $from, $newsletter, $nlType, $recipients, $messageID);

		return $emailProcess->start();
	}

	/**
	 * Top level call, $param is a SS_HTTPRequest Object
	 *
	 * @todo When is $params an object? Typically it's the form request
	 * data as an array...
	 */
	public function save($params, $form) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		// Both the Newsletter type and the Newsletter draft call save() when "Save" button is clicked
		// @todo this is a hack. It needs to be cleaned up. Two different classes shouldn't share the
		// same submit handler since they have different behaviour!
		$type = isset($this->request['Type']) ? $this->request['Type'] : 'NewsletterType';
		switch($type) {
			case 'Article':
				return $this->savearticle($form);
			case 'Newsletter':
				return $this->savenewsletter($form);
			default:
				return $this->savenewslettertype($form);
		}
	}

	/*
	 * Internal call found so far.
	 */
	public function savenewsletter($form) {
		$this->setNewsletterFromRequest();

		// Is the template attached to the type, or the newsletter itself?
		$type = $this->newsletter->getNewsletterType();

		$form->saveInto($this->newsletter);
		$this->newsletter->Subject = $this->request['Subject'];
		$this->newsletter->Content = $this->request['Content'];
		$this->newsletter->write();

		$id = 'draft_'.$this->newsletter->ParentID.'_'.$this->newsletter->ID;

		FormResponse::set_node_title($id, $this->newsletter->Title);
		FormResponse::status_message('Saved', 'good');
		// Get the new action buttons
		$actionList = '';
		foreach($form->Actions() as $action) {
			$actionList .= $action->Field() . ' ';
		}
		FormResponse::add("$('Form_EditForm').loadActionsFromString('" . Convert::raw2js($actionList) . "');");
		return FormResponse::respond();
	}

	public function savenewslettertype($form) {
		$this->setNewsletterTypeFromRequest();

		// Is the template attached to the type, or the newsletter itself?
		$this->newsletterType->Template = addslashes($this->request['Template']);

		$form->saveInto($this->newsletterType);
		$this->newsletterType->write();

		FormResponse::set_node_title("mailtype_$this->currentID", $this->newsletterType->Title);
		FormResponse::status_message(_t('NewsletterAdmin.SAVED','Saved'), 'good');
		FormResponse::add($this->getActionUpdateJS($this->newsletterType));
		return FormResponse::respond();
	}
	
	public function savearticle($form) {
		$this->setNewsletterArticleFromRequest();
		$this->newsletterArticle->Title = $this->request['Title'];
		$this->newsletterArticle->Body = $this->request['Body'];
		$form->saveInto($this->newsletterArticle);

		$this->newsletterArticle->write();

		// Get the new action buttons
		$actionList = '';
		foreach($form->Actions() as $action) {
			$actionList .= $action->Field() . ' ';
		}
		FormResponse::add("$('Form_EditForm').loadActionsFromString('" . Convert::raw2js($actionList) . "');");
		FormResponse::set_node_title( 'article_' . $this->newsletterArticle->ID, $this->newsletterArticle->Title);
		FormResponse::status_message(_t('NewsletterAdmin.SAVED'), 'good');
		return FormResponse::respond();
	}

	/*
	 * Saves the settings on the 'Bounced' tab of the 'Mailing List' allowing members to be added to NewsletterEmailBlacklist
	 *
	 */
	public function memberblacklisttoggle($params) {
		$this->setCachedRequest($params);
		$this->setCurrentPageIDFromRequest();

		$bounceObject = DataObject::get_by_id('Email_BounceRecord', $this->currentID);
		if ($bounceObject) {
			$memberObject = DataObject::get_by_id('Member', $bounceObject->MemberID);
			if ($memberObject) {
				// If the email is currently not blocked, block it
				if (FALSE == $memberObject->BlacklistedEmail) {
					$memberObject->setBlacklistedEmail(TRUE);
					FormResponse::status_message($memberObject->Email.' '._t('NewsletterAdmin.ADDEDTOBL', 'was added to blacklist'), 'good');
				} else {
					// Unblock the email
					$memberObject->setBlacklistedEmail(FALSE);
					FormResponse::status_message($memberObject->Email.' '._t('NewsletterAdmin.REMOVEDFROMBL','was removed from blacklist'), 'good');
				}
			}
		}

		return FormResponse::respond();
	}

  	public function NewsletterAdminSiteTree() {
	  return $this->getsitetree();
  	}

  	public function getsitetree() {
	  return $this->renderWith('NewsletterAdmin_SiteTree');
  	}

	/**
	 * This method is called when a user changes subsite in the dropdownfield.
	 * It is added temporarily to prevent error when changing subsite in newsletter admin
	 * TODO: fully implement it to display the newsletter tree
	 */
	public function SiteTreeAsUL() {
		return "Please refresh the page";
	}

	public function AddRecordForm() {
		$m = new MemberTableField($this, "Members", $this->currentPageID());
		return $m->AddRecordForm();
	}

	/**
	 * Ajax autocompletion
	 */
	public function autocomplete() {
		$fieldName = $this->urlParams['ID'];
		$fieldVal = $_REQUEST[$fieldName];

		$matches = DataObject::get("Member","\"$fieldName\" LIKE '" . Convert::raw2sql($fieldVal) . "%'");
		if($matches && $matches->exists()) {
			echo "<ul>";
			foreach($matches as $match) {
				echo	"<li>"
							. $match->$fieldName
							. "<span class=\"informal\">($match->FirstName $match->Surname, $match->Email)</span>"
							. "<span class=\"informal data\">$match->FirstName,$match->Surname,$match->Email,$match->Password</span>"
						. "</li>";
			}
			echo "</ul>";
		}
	}

	function savemember() {
		$this->setCachedRequest();
		$this->setCurrentPageIDFromRequest();

		$className = $this->stat('subitem_class');

		if ($this->currentID) {
			$record = DataObject::get_one($className, "\"$className\".\"ID\" = $id");
		} else {
			// send out an email to notify the user that they have been subscribed
			$record = new $className();
		}

		$record->update($this->request);
		if ($this->currentID) {
			$record->ID = $this->currentID;
		}
		$record->write();

		$record->Groups()->add($this->request['GroupID']);

		$FirstName = Convert::raw2js($record->FirstName);
		$Surname = Convert::raw2js($record->Surname);
		$Email = Convert::raw2js($record->Email);
		$Password = Convert::raw2js($record->Password);
		$response = <<<JS
			$('MemberList').setRecordDetails($record->ID, {
				FirstName : "$FirstName",
				Surname : "$Surname",
				Email : "$Email"
			});
			$('MemberList').clearAddForm();
JS;
		FormResponse::add($response);
		FormResponse::status_message(_t('NewsletterAdmin.SAVED'), 'good');

		return FormResponse::respond();
	}


	public function NewsletterTypes() {
		return DataObject::get("NewsletterType");
	}

	/**
	 * Called by AJAX to create a new newsletter article
	 * Top level call
	 */
	public function addarticle($request) {
		$this->setCachedRequest($request);
		$this->setCurrentPageIDFromRequest();

		$ID = intval($request->getVar('ParentID'));
		$securityMsg = null;

		if ($ID) {
			$newsletter = DataObject::get_by_id('Newsletter', $ID);
			if ($newsletter) {
				// It should be safe to assume that if you can create newsletters you can create articles
				if(!$newsletter->canCreate()) {
					$securityMsg = 'Sorry, you do not have permission to create articles';
				}
			}
			else {
				$securityMsg = 'Invalid newsletter ID';
			}
		}
		else {
			$securityMsg = 'Invalid newsletter ID';
		}

		if ($securityMsg) {
			Security::permissionFailure(null, $securityMsg);
			return $securityMsg;
		}

		$article = $newsletter->createArticle();
		$form = $this->getArticleEditForm($article->ID);
		return $this->showWithEditForm($form);
	}

	/**
	 * Called by AJAX to create a new newsletter type
	 * Top level call
	 */
	public function addtype($params) {
		$this->setCachedRequest($params);

		$this->currentID = $this->newNewsletterType();

		$form = $this->getNewsletterTypeEditForm($this->currentID);
		return $this->showWithEditForm($form);
	}

	/**
	 * Called by AJAX to create a new newsletter draft
	 * Top level call
	 */
	public function adddraft($params) {
		$this->setCachedRequest();

		$draftID = $this->newDraft($this->request['ParentID']);
		// Needed for shownewsletter() to work
		$this->currentID = $draftID;
		return $this->shownewsletter($params);
	}

	/**
	* Create a new newsletter type
	*/
	private function newNewsletterType() {
		// create the new type
		$newsletterType = new NewsletterType();
		$newsletterType->Title = _t('NewsletterAdmin.NEWNEWSLTYPE','New newsletter type');
		$newsletterType->write();

		// BUGFIX: Return only the ID of the new newsletter type
		return $newsletterType->ID;
	}

   private function newDraft($parentID) {
		if (!($parentID && is_numeric($parentID))) {
			$parent = DataObject::get_one("NewsletterType");
			if ($parent) {
				$parentID = $parent->ID;
			} else {
				// BUGFIX: It could be that no Newsletter types have been created, if so add one to prevent errors.
				$parentID = $this->newNewsletterType();
			}
		}
		if($parentID && is_numeric($parentID)) {
			$parent = DataObject::get_by_id("NewsletterType", $parentID);
			if($parent) {
				$newsletter = new Newsletter();
				$newsletter->Status = 'Draft';
				$newsletter->Title = $newsletter->Subject = _t('NewsletterAdmin.MEWDRAFTMEWSL','New draft newsletter');
				$newsletter->ParentID = $parentID;
				$newsletter->write();
			}
			else {
				user_error("No newsletter type found for ID $parentID", E_USER_ERROR);
			}
		} else {
			user_error("You must first create a newsletter type before creating a draft", E_USER_ERROR);
		}

		return $newsletter->ID;
	}

	public function newmember() {
		Session::clear('currentMember');
		$newMemberForm = array(
			"MemberForm" => $this->getMemberForm('new'),
		);

		if(Director::is_ajax()) {
			SSViewer::setOption('rewriteHashlinks', false);
			$customised = $this->customise($newMemberForm);
			$result = $customised->renderWith($this->class . "_rightbottom");
			$parts = split('</?form[^>]*>', $result);
			echo $parts[1];

		} else {
			return $newMemberForm;
		}
	}

	public function EditedMember() {
		if(Session::get('currentMember'))
			return DataObject::get_by_id("Member", Session::get('currentMember'));
	}

	public function Link($action = null) {
		return 'admin/newsletter/';
	}

	public function displayfilefield() {
		$id = $this->urlParams['ID'];

		return $this->customise(array('ID' => $id, "UploadForm" => $this->UploadForm()))->renderWith('Newsletter_RecipientImportField');
	}

	function UploadForm( $id = null ) {
		if (!$id) {
			$id = $this->urlParams['ID'];
		}

		$fields = new FieldSet(
			new FileField("ImportFile", ""),
			new HiddenField("ID", "", $id)
		);

		$actions = new FieldSet(
			new FormAction("action_import", _t('NewsletterAdmin.SHOWCONTENTS','Show contents'))
		);

		return new RecipientImportField_UploadForm($this, "UploadForm", $fields, $actions);
	}

	function getMenuTitle() {
		return _t('LeftAndMain.NEWSLETTERS',"Newsletters",PR_HIGH,"Menu title");
	}
	
	public function orderchange($request) {
		$postdata = $request->postVars();
		$neworder = $postdata['ID'];
		$counter  = 1;
		
		// ids are coming through as article_{id} pull out the int part of the string
		foreach ($neworder as &$id) {
			if (preg_match('/\d+/', $id, $matches)) {
				$id = $matches[0];
				DB::query("UPDATE NewsletterArticle SET SortOrder = $counter WHERE ID = $id");
				++$counter;
			}
		}
		
		FormResponse::status_message(_t('LeftAndMain.SAVED'), 'good');
		return FormResponse::respond();
	}
	
	public function parentchange($request) {
		$postdata = $request->postVars();
		
		$id = null;
		$parent = null;
		
		if (preg_match('/\d+/', $postdata['ID'], $id_matches)) {
			$id = $id_matches[0];
		}

		if (preg_match('/\d+$/', $postdata['ParentID'], $parent_matches)) {
			$parent = $parent_matches[0];
		}

		if ($id !== null && $parent !== null) {
			DB::query("UPDATE NewsletterArticle SET NewsletterID = $parent WHERE ID = $id");
		}

		FormResponse::status_message(_t('LeftAndMain.SAVED'), 'good');
		return FormResponse::respond();
	}

	public function setCurrentPageIDFromRequest() {
		$request = $this->getCachedRequest();
		$id = 0;

		if (isset($request['ID'])) {
			$id = $request['ID'];
		}
		else if (isset($request['NewsletterID'])) {
			$id = $request['NewsletterID'];
		}

		$this->currentID = intval($id);
		$this->setCurrentPageID($this->currentID);
	}

	public function setNewsletterFromRequest() {
		if (!$this->newsletter) {
			if ($this->currentID) {
				$this->newsletter = DataObject::get_by_id('Newsletter', $this->currentID);
			}

			if(!$this->newsletter) {
				$this->newsletter = new Newsletter;
			}
		}
	}

	public function setNewsletterArticleFromRequest() {
		if (!$this->newsletterArticle) {
			if ($this->currentID) {
				$this->newsletterArticle = DataObject::get_by_id('NewsletterArticle', $this->currentID);
			}

			if(!$this->newsletterArticle) {
				$this->newsletterArticle = new NewsletterArticle;
			}
		}
	}

	public function setNewsletterTypeFromRequest() {
		if (!$this->newsletterType) {
			if ($this->currentID) {
				$this->newsletterType = DataObject::get_by_id('NewsletterType', $this->currentID);
			}

			if(!$this->newsletterType) {
				$this->newsletterType = new NewsletterType;
			}
		}
	}

	protected function getCachedRequest() {
		return $this->request ?: $_REQUEST;
	}

	protected function setCachedRequest($params = null) {
		if ($params) {
			$this->request = $params instanceof SS_HTTPRequest
				? $params->allParams()
				: $params;
		}
		else {
			$this->request = $_REQUEST;
		}
	}
}
