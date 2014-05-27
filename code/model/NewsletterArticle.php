<?php
/**
 * @package  newsletter
 */

/**
 * Single newsletter article instance. 
 */
class NewsletterArticle extends DataObject {
	private static $db = array(
		'Title'    => 'Varchar(255)',
		'Content'  => 'HTMLText'
	);

	private static $has_one = array(
		'Newsletter' => 'Newsletter'
	);
}