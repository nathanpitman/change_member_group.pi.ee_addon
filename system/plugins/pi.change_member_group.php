<?php

$plugin_info = array(
  'pi_name' => 'Change Member Group',
  'pi_version' => '1.0',
  'pi_author' => 'Nine Four',
  'pi_author_url' => 'http://www.ninefour.co.uk/labs',
  'pi_description' => 'Allows you to change a members group from within a front end page template.',
  'pi_usage' => Change_member_group::usage()
  );

class Change_member_group
{

	// Config
	var $invalid_member = "<strong>Error:</strong> The member id provided is invalid.";
	var $invalid_group = "<strong>Error:</strong> The group id provided is invalid.";
	var $locked_group = "<strong>Error:</strong> The specified member group is locked.";
	var $fatal_error = "<strong>Error:</strong> There was a problem making the requested member group change.";
	// End config 
	
	var $user_message = "";
  
	// Generate an member-specific change member group link  
	function link()
	{
		global $TMPL, $PREFS;
		
		if (!$TMPL->fetch_param('member_id') OR !$TMPL->fetch_param('group_id') OR !$TMPL->fetch_param('template') OR !$TMPL->fetch_param('return_url')) {
			return "Invalid change_member_group plugin usage.";
		} else {
		
			// Collect params
			$member_id = $TMPL->fetch_param('member_id');
			$group_id = $TMPL->fetch_param('group_id');
			$template = $TMPL->fetch_param('template');
			$return_url = $TMPL->fetch_param('return_url');
			$show_confirm = ($TMPL->fetch_param('show_confirm') ? $TMPL->fetch_param('show_confirm') : 'true');
			$class = ($TMPL->fetch_param('class') ? $TMPL->fetch_param('class') : '');
			
			// Build an encoded change string to prevent happy hacking
			$change_string = base64_encode($member_id."~".$group_id."~".$return_url);
			
			// Determine the phrase used for the confirm dialog. 
			$confirm_message = ($TMPL->fetch_param('confirm_message') ? $TMPL->fetch_param('confirm_message') : 'Are you sure you want to change your member group?');
			
			// Generate a javascript confirmation box unless the user requests otherwise
			if($show_confirm != "false") {
				$show_confirm = " onclick=\"javascript: if (!confirm('$confirm_message')) return false;\"";
			}
			
			if($class) {
				$class = " class=\"$class\"";
			} 
			
			if ($PREFS->ini('site_index')=="") {
				$site_index = "";
			} else {
				$site_index = $PREFS->ini('site_index') . "/";
			}
			
			$link = "<a href=\"" . $PREFS->ini('site_url') . $site_index . 
			$TMPL->fetch_param('template') . "/" . $change_string . "\"" . $show_confirm . $class . ">" . $TMPL->tagdata . "</a>";
			return $link;
				
		}
	}
  
	private function _getLastSegment($uri) {
		if (!strlen($uri)) return '';
		
		if (substr($uri,-1,1) == '/') {
			$uri = substr($uri,0,-1);
		}
		return substr($uri,(strrpos($uri, '/')+1));
	}
  
	function change()
	{
    	global $IN, $TMPL, $DB, $FNS, $SESS, $PREFS; 
	 
		if (!$TMPL->fetch_param('group_id')) {
			
			$mode = "link";
			
			$encoded_change_string = $this->_getLastSegment($FNS->fetch_current_uri());
	 
			// Deconstruct the encoded change string
			$decoded_change_string = base64_decode($encoded_change_string);
			
			$parts = explode("~", $decoded_change_string);
	
			$member_id = (int)$parts[0];
			$group_id = (int)$parts[1];
			$return_url = $parts[2];
			
		} else {
		
			$mode = "instant";
			
			// Collect params from template tag and session instead
			if (!$TMPL->fetch_param('member_id')) {
				$member_id = $SESS->userdata['member_id'];
			} else {
				$member_id = (int)$TMPL->fetch_param('member_id');
			}
			$group_id = (int)$TMPL->fetch_param('group_id');

		}
		
		// Does the member id provided even exist?
		$query = $DB->query("SELECT member_id, group_id FROM exp_members WHERE member_id = $member_id");
		if ($query->num_rows < 1) {	 
			$this->usermessage .= $this->invalid_member;
			return $this->usermessage;
		}
		
		// Is the member specified already assigned to the member group that has been provided?
		// Is the member specified a super admin, we don't want any nasty accidents do we!
		if(($query->row['group_id'] != $group_id) OR ($query->row['group_id'] == 1)) {
			
			// Check user's permissions allow them to be assigned to the specified member group
			if($SESS->userdata['group_id'] != 1) { // Superadmins automatically bypass access level checks
			
				// Is this user allowed to change to the group specified, ie... is it unlocked... is the group id even valid?
				$query = $DB->query("SELECT is_locked FROM exp_member_groups WHERE group_id = $group_id");
				if ($query->num_rows < 1) {	 
					$this->usermessage .= $this->invalid_group;
					return $this->usermessage;
				} else {
					if ($query->row['is_locked']=="y") {
						$this->usermessage .= $this->locked_group;
						return $this->usermessage;
					}
				}
	
			}
	
			// Do the member group assignment update!
			$query = $DB->query("UPDATE exp_members set group_id = '".$group_id."' WHERE member_id = '".$member_id."'");
	
			// Show a error message
			if($query!=1) {
				$this->usermessage = $this->fatal_error;
				return $this->usermessage;
			} else {
			// If no error redirect or do nothing, depending on usage type
				if ($mode=="link") {
					$FNS->redirect($return_url);
				}
			
			}
			
		}
		
	}

	// ----------------------------------------
	//  Plugin Usage
	// ----------------------------------------

	function usage() {
	ob_start(); 
	?>
	The change member group plugin allows you to change the member group that a user is assigned to through the front end page templates if the member group you wish to assign them to is unlocked. You can use the plug-in in two ways, to generate a 'link' which a users clicks on to effect the change, or have the change implemented immediately upon visiting a template with the relevant 'change' tag in place:

GENERATE CHANGE LINK - {exp:change_member_group:link}

This tag is used in pairs and generates the URL to the member group change page. There are four available parameters with the link function:

 - member_id (Required) - The id of the member to change the member group of. E.g. 1
 = group_id (Required) - The id of the member group that you wish to assign members to. E.g. 5
 - template (Required) - The name of the template containing the {exp:change_member_group:change} plugin code. E.g. site/change_member_group
 - show_confirm (Optional) - If set to true then the JavaScript confirmation dialog will appear
 - confirm_message (Optional) - Use this to override the default JavaScript confirm dialog text.
 - class (Optional) - Allows you to add a CSS class to the link that is generated.

Example usage: 
{exp:change_member_group:link member_id="{member_id}" group_id="5" template="site/change_member_group" confirm_message="Are you sure you want to change your member group?" return_url="/site/index"}Change my member group...{/exp:change_member_group:link}


CHANGE THE MEMBER GROUP - {exp:change_member_group:change}

This tag should be embedded in a special 'change member group' template. You can wrap as little or as much HTML around the tag as you like to ensure that the page is styled to suit your site. If you are using this tag with no parameters you must have linked to this template using the 'link' tag and have provided a return_url value in the link tag. To use this tag on it's own you must provide a group_id and can optionally provide a member_id. If no member_id is provided then the plug-in will by default attempt to modify the currently logged in members group. All of the standard user EE access checks are completed before a user is allowed to modify their member group, so ensure that your member group permissions are set correctly. As a precaution the plug-in does not allow a Super Admin to modify their member group.

This function only has two parameters:

 - member_id (Optional) - If this is set then the specified member_id will be affected, if left empty the plug-in will attempt to modify the member group of the currently logged in member.
 - group_id (Optional) - Optional but must be specified if you are using the change tag in a standalone form to effect an instant member group change on template load.
 
Example usage:
 
{exp:change_member_group:change group_id="5"}

IMPORTANT NOTE

Given that this plug-in allows the modification of member data and is dependant on member group permissions being correctly configured you should take great care to ensure these are set up correctly. Care should also be exercised with the link tag as users will receive no prompt if javascript is not enabled in their browser (or if confirm_message is not specified as a parameter). 

  <?php
  $buffer = ob_get_contents();
	
  ob_end_clean(); 

  return $buffer;
  }
  // END

}

?>