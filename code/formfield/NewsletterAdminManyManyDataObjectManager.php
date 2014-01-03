<?php
/**
 * The NewsletterAdmin module's handling of request parameter has been designed
 * in such a way that it works poorly with the ComplexTableField and its children.
 * Features such as search and only_related do not have a link to the
 * Newsletter so they do not work.
 */
class NewsletterAdminManyManyDataObjectManager extends ManyManyDataObjectManager {
	protected $newsletterClass = 'Newsletter';

	// Add the Newsletter ID to the Add links
	public function AddLink() {
	    return Controller::join_links(
			$this->BaseLink(),
			'add',
			'?DataObjectManagerId='.$this->id() . $this->newsletterIDQueryArgument()
		);
	}

	// Add the Newsletter ID to the wrapping div
	// This is used by the search functionality
	public function CurrentLink() {
		return parent::CurrentLink() . $this->newsletterIDQueryArgument();
	}

	public function newsletterIDQueryArgument() {
		return '&NewsletterID=' . $this->controller->ID;
	}

	// Add the Newsletter ID to links
	// This is used by only_related
	public function RelativeLink($params = array()) {
		return parent::RelativeLink($params) . $this->newsletterIDQueryArgument();
	}
}