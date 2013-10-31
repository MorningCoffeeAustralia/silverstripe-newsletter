<?php

/**
 * Description of NewsletterArticleAdmin
 *
 * @author BJMMac
 */
class NewsletterArticleAdmin extends ModelAdmin {
  public static $managed_models = array(   //since 2.3.2
      'NewsletterArticle'
   );
 
  static $url_segment = 'newsletterarticle'; // will be linked as /admin/products
  static $menu_title = ' ';
}
