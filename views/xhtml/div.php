<?php
/**
* @file views/xhtml/div.php
* @author Ivan Vergés
* @brief \<div> tag for the default XHTML view\n
*
* @section usage Usage
* echo m_view("div",array('id'=>"div1",'class'=>"my_class",'body'=>"DIV html content"));\n
* //or\n
* echo m_view("div","DIV html content");
*
* @param id html id
* @param class html label class
* @param title html label title
* @param style html label style
* @param body html content inside \<div>...\</div>
*/
if(is_array($vars)) $body = $vars['body'];
else {
	$body = $vars;
	$vars = array();
}

echo '<div';
//Id
echo ($vars['id'] ? ' id="'.$vars['id'].'"' : '');
//class
echo ($vars['class'] ? ' class="'.$vars['class'].'"' : '');
//title
echo ($vars['title'] ? ' title="'.htmlspecialchars($vars['title']).'"' : '');
//style
echo ($vars['style'] ? ' style="'.$vars['style'].'"' : '');

echo '>';

echo $body;

echo '</div>';
?>
