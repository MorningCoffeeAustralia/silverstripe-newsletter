<?php
class NewsletterArticle extends DataObject {
	public static $db = array(
		'Title' => 'Varchar(100)',
		'Body' => 'HTMLText'
	);

	public static $has_one = array (
		'Image' => 'BetterImage',
		'Newsletter' => 'Newsletter'
	);
	
	public static $defaults = array(
		'Title' => 'New Article'
	);

	public function getCMSFields($params = null) {
		$fields = parent::getCMSFields($params);

		$fields->removeByName('Newsletter');

		return $fields;
	}

	public function getLabel() {
		return $this->Title ? $this->Title : "(Article)";
	}

	/**
	 * Checks if any article in the newsletter has an image
	 *
	 * @return bool
	 */
	public function NewsletterHasImages() {
		return $this->Newsletter()->hasImages();
	}
}