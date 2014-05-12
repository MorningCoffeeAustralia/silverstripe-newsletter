<?php
/**
 * @package  newsletter
 */

/**
 * Single newsletter article instance. 
 */
class NewsletterArticle extends DataObject {
	static $db = array(
		'Title'    => 'Varchar(255)',
		'Content'  => 'HTMLText'
	);

	static $has_one = array(
		'Newsletter' => 'Newsletter'
	);
}