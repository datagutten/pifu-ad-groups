<?Php
chdir(__DIR__);
require 'vendor/autoload.php';
$pifu=new pifu_parser();
$adtools=new adtools_groups('groups');

$config = require 'config.php';

foreach($pifu->schools() as $school)
{
	$groups=$pifu->groups($school->sourcedid->id,1);
	
	if(!empty($config['extra_groups']))
    {
        foreach($config['extra_groups'] as $group)
        {
            $group = $pifu->group_info($school, $group);
            if(!empty($group))
                $groups[] = $group;

        }
    }

	if(empty($groups))
		continue;
	foreach($groups as $group)
	{
		echo $group->comments."\n";
		//Create group for users
		$group_name=$group->relationship->label.' '.$group->description->short;
		$ad_user_group_name=sprintf($config['group_name'], $group_name);
		try {
            $ad_user_group_dn=$adtools->create_group_if_not_exists($ad_user_group_name,$config['ou_users']);
        }
		catch (LdapException $e)
        {
            echo 'Failed to create group: '.$e->getMessage()."\n";
            continue;
        }
        try {
            $adtools->member_del(array(),$ad_user_group_dn); //Empty the group before adding new members
        }
        catch (Exception $e)
        {
            echo $e->getMessage()."\n";
            continue;
        }

		foreach($pifu->group_members($group, $options = array('roletype'=>'01')) as $member)
		{
			$guid=str_replace('person_','',$member->sourcedid->id);
			//$ad_user=user_cache_lookup($guid); //Find user in AD
            //$ad_user = $adtools->query(sprintf('(%s=%s)', $config['guid_field'], $guid), $config['ou_users'], array('dn'));
            try {
                $ad_user = $adtools->ldap_query(sprintf('(%s=%s)', $config['guid_field'], $guid), array(
                        'base_dn' => $config['ou_users'],
                        'attributes' => array('dn'))
                );
                //var_dump($ad_user);
            }
            catch (NoHitsException $e)
            {
                printf("AD user not found for %sin group %s role %s\n", $member->comments, $group->comments, $member->role->attributes()['roletype']);
                continue;
            }
            catch (LdapException|MultipleHitsException|Exception $e)
            {
                printf("Error looking up GUID: %s\n", $e->getMessage());
                continue;
            }

            try {
                $adtools->member_add($ad_user,$ad_user_group_dn); //Add the user to the group
            }
            catch (LdapException $e)
            {
                printf("Unable to add %s to %s: %s\n", $ad_user, $ad_user_group_dn, $e->getMessage());
            }
		}
	}
}
