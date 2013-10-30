<?php
class NewsletterArticle extends DataObject {
	public static $db = array(
		'Title' => 'Varchar(100)',
		'Body' => 'Text'
	);

	public static $has_one = array (
		'Image' => 'BetterImage',
		'Newsletter' => 'Newsletter'
	);
	
	public static $defaults = array(
		'Title' => 'New Article'
	);

	public function getNewsletterArticleEditForm() {
		$fields = $this->getCMSFields();
//		$test = $this->exists();
//		if( $this->exists() != 1 ) {
////			$fields->removeFieldFromTab('Root.Main', 'Image');
//			$fields->removeByName('Image');
//		}
		$fields->push($field = new HiddenField("ID"));
		$field->setValue($this->ID);

		$actions = $this->getCMSActions();

		$form = new Form($this, "NewsletterArticleEditForm", $fields, $actions);
		$form->loadDataFrom($this);

		$newsletter = $this->Newsletter();
		if($newsletter->Status != 'Draft') {
			$readonlyFields = $form->Fields()->makeReadonly();
			$form->setFields($readonlyFields);
		}

		return $form;
	}
	
	public function link() {
		return '$this->Image()->Link()';
	}
}