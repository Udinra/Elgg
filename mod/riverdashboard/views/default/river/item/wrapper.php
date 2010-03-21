<?php
/**
 * Elgg river item wrapper.
 * Wraps all river items.
 */

//set required variables
$object = get_entity($vars['item']->object_guid);
//get object url
$object_url = $object->getURL();
//user
//if displaying on the profile get the object owner, else the subject_guid
if(get_context() == 'profile' && $object->getSubtype() ==  'thewire')
	$user = get_entity($object->owner_guid);
else
	$user = get_entity($vars['item']->subject_guid);

//get the right annotation type
//*todo - use the same for comments, everywhere e.g. comment
switch($vars['item']->subtype){
	case 'thewire':
	$annotation_comment = 'wire_reply';
	break;
	default:
	$annotation_comment = 'generic_comment';
	break;
}

//count comment annotations
$comment_count = count_annotations($vars['item']->object_guid, $vars['item']->type, $vars['item']->subtype, $annotation_comment);

//get last two comments display
$get_comments = get_annotations($vars['item']->object_guid, "", "", $annotation_comment, "", "", 3, 0, "desc");

if($get_comments){
	//reverse the array so we can display comments in the right order
	$get_comments = array_reverse($get_comments);	
}

//minus two off the comment total as we display two by default
if($comment_count < 3)
	$num_comments = 0;
else
	$num_comments = $comment_count - 3;
?>
<div class="river_item">
	<span class="river_item_useravatar">
		<?php echo elgg_view("profile/icon",array('entity' => $user, 'size' => 'small')); ?>
	</span>
	<div class="river_item_contents clearfloat">
		<!-- body contents, generated by the river view in each plugin -->
		<?php echo $vars['body']; ?>
	</div>
	
<!-- display comments and likes -->
<?php
	//likes
	echo "<div class='river_comments'>";
	echo "<div class='river_comment latest clearfloat'>";
	echo elgg_view_likes($object);
	echo "</div></div>";
	//display latest 2 comments if there are any
	if($get_comments){
		$counter = 0;
		$background = "";
		echo "<div class='river_comments'>";
			
		//display the number of comments if there are any
		if($num_comments != 0){
			//set the correct context comment or comments
			if($num_comments == 1)
				echo "<a class='river_more_comments' href=\"{$object_url}\">+{$num_comments} more comment</a>";
			else
				echo "<a class='river_more_comments' href=\"{$object_url}\">+{$num_comments} more comments</a>";
		}
			
		foreach($get_comments as $gc){
			//get the comment owner
			$comment_owner = get_user($gc->owner_guid);
			//get the comment owner's profile url
			$comment_owner_url = $comment_owner->getURL();
			// color-code each of the 3 comments
			if( ($counter == 2 && $comment_count >= 4) || ($counter == 1 && $comment_count == 2) || ($counter == 0 && $comment_count == 1) || ($counter == 2 && $comment_count == 3) )
				$alt = 'latest';
			else if( ($counter == 1 && $comment_count >= 4) || ($counter == 0 && $comment_count == 2) || ($counter == 1 && $comment_count == 3) )
				$alt = 'penultimate';
			
			//display comment
			echo "<div class='river_comment {$alt} clearfloat'>";
			echo "<span class='river_comment_owner_icon'>";
			echo elgg_view("profile/icon",array('entity' => $comment_owner, 'size' => 'tiny'));
			echo "</span>";
			//truncate comment to 150 characters
			if(strlen($gc->value) > 150) {
		        	$gc->value = substr($gc->value, 0, strpos($gc->value, ' ', 150)) . "...";
		    }
			$contents = strip_tags($gc->value);
		    echo "<div class='river_comment_contents'>";
			echo "<a href=\"{$comment_owner_url}\">" . $comment_owner->name . "</a> " . parse_urls($contents);
			echo "<span class='entity_subtext'>" . friendly_time($gc->time_created) . "</span>";
			echo "</div></div>";
			$counter++;
		}
			echo "</div>";
		}
			//display the comment link
		if($vars['item']->type != 'user'){
			//for now don't display the comment link on wire and conversations for now
			if($vars['item']->subtype != 'thewire' && $vars['item']->subtype != 'conversations' && $vars['item']->subtype != '')
				//don't display the comment option on group discussions atm
				if($vars['item']->subtype == 'groupforumtopic'){
					echo "<a class='comment_link' href=\"{$object_url}\">Visit discussion</a>";
				}else{
					echo "<div class='river_post_comment'>";
					echo elgg_make_river_comment($object);
					echo "</div>";
				}
			}
		?>
</div>