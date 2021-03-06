<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');

/**
 * 
 * 
 * @copyright		[PHPFOX_COPYRIGHT]
 * @author  		Raymond Benc
 * @package  		Module_User
 * @version 		$Id: index.class.php 1179 2009-10-12 13:56:40Z Raymond_Benc $
 */
class User_Component_Controller_Admincp_Group_Index extends Phpfox_Component 
{
	/**
	 * Controller
	 */
	public function process()
	{	
		$this->template()->setBreadcrumb(Phpfox::getPhrase('user.user_groups'), $this->url()->makeUrl('admincp.user.group'))
			->setBreadcrumb(Phpfox::getPhrase('user.manage_user_groups'), null, true)
			->setTitle(Phpfox::getPhrase('user.manage_user_groups'))
			->setSectionTitle(Phpfox::getPhrase('user.manage_user_groups'))
			->setActionMenu([
				'Create User Group' => [
					'class' => 'popup',
					'url' => $this->url()->makeUrl('admincp.user.group.add')
				]
			])
			->assign(array(
				'aGroups' => Phpfox::getService('user.group')->getForEdit(),
				// 'sSectionTitle' => Phpfox::getPhrase('user.manage_user_groups')
			)
		);
	}
}

?>