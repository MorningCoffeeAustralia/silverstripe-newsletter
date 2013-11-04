<?php

class NewsletterArticleAdmin extends ModelAdmin {
	public static $managed_models = array(
		'NewsletterArticle'
	);

	public static $url_segment = 'newsletterarticle';
}