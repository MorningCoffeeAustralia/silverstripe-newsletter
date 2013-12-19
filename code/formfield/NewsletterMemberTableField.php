<?php
/**
 * The NewsletterAdmin module's handling of request parameter has been designed
 * in such a way that it works poorly with the ComplexTableField and its children.
 * Features such as search do not have a link to the Newsletter so they do not work.
 */
class NewsletterAdminMemberTableField extends MemberTableField {
	// Add the Newsletter ID to the wrapping div
	// This is used by the search functionality
	public function SearchForm() {
		$this->controller->setNewsletterFromRequest();

		$input = '<input type="hidden" name="ID" value="' . $this->controller->newsletter->ID . '" />';

		return preg_replace('/<input/', "$input <input", parent::SearchForm(), 1);
	}
}