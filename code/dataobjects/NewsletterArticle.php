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
		$fields->addFieldToTab( 'Root.Main', $field = new HiddenField("ID"));
		$field->setValue($this->ID);
		
		$fields->removeByName("Image");
		$fields->addFieldToTab( 'Root.Main', new SimpleImageField( 'Image', 'Image') );
		$actions = new FieldSet(new FormAction('save', _t('NewsletterAdmin.SAVE', 'Save')));
		
		// keeping form name as EditForm
		// this hooks into the NewsletterAdmin_right.js to tigger saves
		$form = new Form($this, "EditForm", $fields, $actions);
		$form->loadDataFrom($this);
		
		$fields->addFieldToTab('Root.Main', new HiddenField( 'Type', 'Type', 'Article' ) );
		
		$newsletter = $this->Newsletter();
		if($newsletter->Status != 'Draft') {
			$readonlyFields = $form->Fields()->makeReadonly();
			$form->setFields($readonlyFields);
		}

		return $form;
	}
	
	public function link() {
		return '$this->Image->Link()';
	}
}