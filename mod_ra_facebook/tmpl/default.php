<?php

/**
 * @module	mod_ra_facebook
 * @author	Charlie Bigley
 * version  1.0.0
 * @website	https://demo.stokeandnewcastleramblers.org.uk
 * @copyleft	Copyleft 2021 Charlie Bigley webmaster@stokeandnewcastleramblers.org.uk All rights reserved.
 * @license	http://www.gnu.org/licenses/gpl.html GNU/GPL

 * 09/08/26 CB Created
 */
// no direct access
defined("_JEXEC") or die("Restricted access");

$url = htmlspecialchars((string) $params->get('url', ''), ENT_QUOTES, 'UTF-8');
$caption = htmlspecialchars((string) $params->get('caption', 'See us on Facebook!'), ENT_QUOTES, 'UTF-8');

echo '<p><img src="images/ra-images/images/icon-facebook.png" alt="icon-facebook"><span style="font-size: 14pt;"><a href="';
echo $url;
echo '" target="_blank" rel="noopener">';
echo $caption;
echo '</a></span></p>';
