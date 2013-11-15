<?php

/**
 * Email object for sending newsletters.
 *
 * @package newsletter
 */
class NewsletterEmail extends Email {

	protected $type;
	protected $newsletter;
	
	static $casting = array(
		'Newsletter' => 'Newsletter',
		'UnsubscribeLink' => 'Text'
	);
	
	/**
	 * @param Newsletter $newsletter
	 * @param NewsletterType $type
	 */
	public function __construct($newsletter, $type = null) {
		$this->newsletter = $newsletter;
		$this->nlType = $type ?: $newsletter->getNewsletterType();
		
		parent::__construct();
		
		$this->body = $newsletter->getContentBody();
		
		$this->populateTemplate(
			new ArrayData(
				array(
					'Member' => $this->getMember(),
					'Newsletter' => $this->Newsletter,
					'UnsubscribeLink' => $this->UnsubscribeLink()
				)
			)
		);
		
		$this->extend('updateNewsletterEmail', $this);
	}

	public function _getMember() {
		if ($to = $this->To()) {
			$member = DataObject::get_one("Member", "\"Email\" = '$to'");
		}
		else {
			// No to address should mean we are in a preview so get the current member
			$member = Member::currentUser();
		}

		return $member;
	}

	public function send($id = null) {
		foreach ($this->extend('onBeforeSend') as $extRet) {
			// If an extension handled the send it will return truthy
			if($extRet) {
				return $extRet;
			}
		}

		parent::send($id);
	}

	/**
	 * @return Newsletter
	 */
	public function Newsletter() {
		return $this->newsletter;
	}
	
	public function UnsubscribeLink(){
		$url = Director::absoluteBaseURL() . 'unsubscribe/index/';

		if ($member = $this->getMember()) {
			if ($member->AutoLoginHash) {
				$member->AutoLoginExpired = date('Y-m-d', time() + (86400 * 2));
				$member->write();
			}
			else {
				$member->generateAutologinHash();
			}

			$url .= "{$member->AutoLoginHash}/{$this->nlType->ID}";
		}

		return $url;
	}
	
	public function getData() {
		return $this->template_data;
	}
}