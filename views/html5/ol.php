<?php
/**
* @file views/html5/ol.php
* @author Ivan Vergés
* @brief \<ol> tag for the default HTML5 view\n
*
* @section usage Example:
* <code>
* echo m_view("ol",array('id'=>"ol1",'class'=>"my_class",'body'=>"OL html content"));\n
* //or\n
* echo m_view("ol","OL html content");\n
* //or\n
* $items = array();\n
* $items[] = "Item 1 content";\n
* $items[] = "Item 2 content";\n
* echo m_view("ol",array('id'=>"ol1",'class'=>"my_class",'items'=>$items));\n
* //or\n
* $items = array();\n
* $items[] = array('id'=>"item1",'title'=>"Item 1 title",'body'=>"Item 1 content");\n
* $items[] = array('id'=>"item2",'title'=>"Item 2 title",'body'=>"Item 2 content");\n
* echo m_view("ol",array('id'=>"ol1",'class'=>"my_class",'items'=>$items));\n
* </code>
*
* @param body html content inside \<ol>...\</ol>
* @param items if body not exists and this is a array, it will be used to display the internal \<li> elements of \<ol>, a the same time \b items could be a array with text or a sub-array with \b id, \b class, \b style, \b title & \b body sub-parameters
*/

if(is_array($vars)) $body = $vars['body'];
else {
	$body = $vars;
	$vars = array();
}

echo '<ol';

require('_common_html5_attributes.php');
require('_common_html5_event_attributes.php');

echo (empty($vars['reversed']) ? '' : ' reversed="reversed"');
echo ($vars['start'] ? ' start="' . intval($vars['start']) . '"' : '');
echo (in_array($vars['type'], array('1', 'A', 'a', 'I', 'i')) ? ' type="' . $vars['type'] . '"' : '');

echo '>';

if($body) echo $body;
elseif(is_array($vars['items'])) {
	foreach($vars['items'] as $item) {
		echo m_view('li', $item);
	}
}
?>
</ol>
