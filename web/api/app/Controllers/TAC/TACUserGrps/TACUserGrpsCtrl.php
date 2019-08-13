<?php

namespace tgui\Controllers\TAC\TACUserGrps;

use tgui\Models\TACUsers;
use tgui\Models\TACUserGrps;
use tgui\Models\MAVISLDAP;
use tgui\Models\APIPWPolicy;
use tgui\Controllers\Controller;
use Respect\Validation\Validator as v;
use Adldap\Adldap as Adldap;
use Adldap\Schemas\ActiveDirectory as AD;
use Adldap\Auth\BindException as ADErr;

class TACUserGrpsCtrl extends Controller
{
################################################
	#########	POST Add New User Group	#########
	public function postUserGroupAdd($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'tacacs user group',
			'action' => 'add',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $res -> withStatus(400) -> write(json_encode($data));
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//
		$policy = APIPWPolicy::select()->first(1);
		$validation = $this->validator->validate($req, [
			'name' => v::noWhitespace()->notEmpty()->userGroupTacAvailable(0)->
				not( v::oneOf(
						v::contains('@'),
						v::contains('='),
						v::contains('*'),
						v::contains('/'),
						v::contains('%'),
						v::contains('$')
					)
				),
			'enable' => v::when( v::nullType() , v::alwaysValid(), v::noWhitespace()->notContainChars()->
				length($policy['tac_pw_length'], 64)->
				notEmpty()->
				passwdPolicyUppercase($policy['tac_pw_uppercase'])->
				passwdPolicyLowercase($policy['tac_pw_lowercase'])->
				passwdPolicySpecial($policy['tac_pw_special'])->
				passwdPolicyNumbers($policy['tac_pw_numbers'])->setName('Enable') ),
			'enable_flag' => v::when( v::nullType() , v::alwaysValid(), v::oneOf( v::equals('1'), v::equals('2'), v::equals('0') ) ),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $res -> withStatus(200) -> write(json_encode($data));
		}

		$allParams = $req->getParams();

		$devices = $allParams['device_list'];
		$services = $allParams['service'];
		$devGroups = $allParams['device_group_list'];
		$ldapGroups = $allParams['ldap_groups'];
		unset($allParams['device_group_list']);
		unset($allParams['service']);
		unset($allParams['device_list']);
		unset($allParams['ldap_groups']);

		if ( ! empty( $allParams['enable'] ) )
		{
			$allParams['enable'] = $this->encryption( $allParams['enable'], $allParams['enable_flag'],  1/*$allParams['enable_encrypt']*/);
		}

		$group = TACUserGrps::create($allParams);

		$devices_bind = [];
		foreach ($devices as $device) {
			$devices_bind[] = ['group_id' => $group->id, 'device_id' => $device];
		}
		$this->db::table('tac_bind_dev')->insert($devices_bind);

		$services_bind = [];
		for ($i=0; $i < count($services); $i++) {
			$services_bind[] = ['tac_grp_id' => $group->id, 'service_id' => $services[$i], 'order' => $i];
		}
		$this->db::table('tac_bind_service')->insert($services_bind);

		$devGrps_bind = [];
		foreach ($devGroups as $devGroup) {
			$devGrps_bind[] = ['group_id' => $group->id, 'devGroup_id' => $devGroup];
		}
		$this->db::table('tac_bind_devGrp')->insert($devGrps_bind);

		$ldap_bind = [];
		foreach ($ldapGroups as $ldapGroup) {
			$ldap_bind[] = ['tac_grp_id' => $group->id, 'ldap_id' => $ldapGroup];
		}
		$this->db::table('ldap_bind')->insert($ldap_bind);

		$data['group']=$group;

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'add', 'obj_name' => $group['name'], 'obj_id' => $group['id'], 'section' => 'tacacs user groups', 'message' => 204);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $res -> withStatus(200) -> write(json_encode($data));
	}
########	Add New User Group	###############END###########
################################################
########	Edit User Group	###############START###########
	#########	GET Edit User Group	#########
	public function getUserGroupEdit($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'tacacs user group',
			'action' => 'edit',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$data['group']=TACUserGrps::select()->where('id',$req->getParam('id'))->first();
		$data['group']->acl = $this->db->table('tac_acl')->
			select(['name as text','id'])->where('id',$data['group']->acl)->get();

		$data['group']['service'] = $this->db::table('tac_bind_service')->
			leftJoin('tac_services as ts','ts.id','=','service_id')->
			select(['ts.id as id', 'ts.name as text'])->where('tac_grp_id',$req->getParam('id'))->get();

		$data['group']['ldap_groups']=$this->db::table('ldap_bind')->
			leftJoin('ldap_groups as ld','ld.id','=','ldap_id')->
			select(['ld.cn as text', 'ld.id as id'])->where('tac_grp_id',$req->getParam('id'))->get();
		// $data['group']['ldap_groups']=$this->db::table('ldap_bind')->select('ldap_id')->where('tac_grp_id',$req->getParam('id'))->pluck('ldap_id')->toArray();

		$data['group']['device_list']=$this->db::table('tac_bind_dev')->
			leftJoin('tac_devices as td','td.id','=','device_id')->
			select(['td.name as text', 'td.id as id'])->where('group_id',$req->getParam('id'))->get();

		$data['group']['device_group_list']=$this->db::table('tac_bind_devGrp')->
			leftJoin('tac_device_groups as tdg','tdg.id','=','devGroup_id')->
			select(['tdg.name as text', 'tdg.id as id'])->where('group_id',$req->getParam('id'))->get();

		return $res -> withStatus(200) -> write(json_encode($data));
	}

	#########	POST Edit User Group	#########
	public function postUserGroupEdit($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'tacacs user group',
			'action' => 'edit',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $res -> withStatus(400) -> write(json_encode($data));
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//
		$policy = APIPWPolicy::select()->first(1);
		$validation = $this->validator->validate($req, [
			'name' => v::noWhitespace()->when( v::nullType() , v::alwaysValid(),
				v::userGroupTacAvailable($req->getParam('id'))->
				not( v::oneOf(
						v::contains('@'),
						v::contains('='),
						v::contains('*'),
						v::contains('/'),
						v::contains('%'),
						v::contains('$')
					)
				)
			),
			'enable' => v::when( v::oneOf( v::nullType(), v::equals('') ), v::alwaysValid(), v::noWhitespace()->notContainChars()->
				length($policy['tac_pw_length'], 64)->
				notEmpty()->
				passwdPolicyUppercase($policy['tac_pw_uppercase'])->
				passwdPolicyLowercase($policy['tac_pw_lowercase'])->
				passwdPolicySpecial($policy['tac_pw_special'])->
				passwdPolicyNumbers($policy['tac_pw_numbers'])->setName('Enable') ),
			'enable_flag' => v::when( v::nullType() , v::alwaysValid(), v::oneOf( v::equals('1'), v::equals('2'), v::equals('0') ) ),
		]);

		if ($validation->failed()){
			$data['error']['status']=true;
			$data['error']['validation']=$validation->error_messages;
			return $res -> withStatus(200) -> write(json_encode($data));
		}

		$allParams = $req->getParams();

		if ( ! empty( $allParams['enable'] ) )
		{
			$allParams['enable'] = $this->encryption( $allParams['enable'], $allParams['enable_flag'], 1 /*$allParams['enable_encrypt']*/);
		}

		$id = $allParams['id'];

		$devices = $allParams['device_list'];
		$services = $allParams['service'];
		$devGroups = $allParams['device_group_list'];
		$ldapGroups = $allParams['ldap_groups'];
		unset($allParams['service']);
		unset($allParams['device_group_list']);
		unset($allParams['device_list']);
		unset($allParams['ldap_groups']);

		$data['save'] = TACUserGrps::where('id',$req->getParam('id'))->
			update($allParams);

			$devices_bind = [];
			foreach ($devices as $device) {
				$devices_bind[] = ['group_id' => $id, 'device_id' => $device];
			}
			$this->db::table('tac_bind_dev')->where('group_id', $id)->delete();
			$this->db::table('tac_bind_dev')->insert($devices_bind);

			$services_bind = [];
			for ($i=0; $i < count($services); $i++) {
				$services_bind[] = ['tac_grp_id' => $id, 'service_id' => $services[$i], 'order' => $i];
			}
			$this->db::table('tac_bind_service')->where('tac_grp_id', $id)->delete();
			$this->db::table('tac_bind_service')->insert($services_bind);

			$devGrps_bind = [];
			foreach ($devGroups as $devGroup) {
				$devGrps_bind[] = ['group_id' => $id, 'devGroup_id' => $devGroup];
			}
			$this->db::table('tac_bind_devGrp')->where('group_id', $id)->delete();
			$this->db::table('tac_bind_devGrp')->insert($devGrps_bind);

			$ldap_bind = [];
			foreach ($ldapGroups as $ldapGroup) {
				$ldap_bind[] = ['tac_grp_id' => $id, 'ldap_id' => $ldapGroup];
			}
			$this->db::table('ldap_bind')->where('tac_grp_id', $id)->delete();
			$this->db::table('ldap_bind')->insert($ldap_bind);

		$data['save'] = 1;

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$name = TACUserGrps::select('name')->where('id',$id)->first();

		$logEntry=array('action' => 'edit', 'obj_name' => $name['name'], 'obj_id' => $id, 'section' => 'tacacs user groups', 'message' => 304);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		return $res -> withStatus(200) -> write(json_encode($data));
	}
########	Edit User Group	###############END###########
################################################
########	Delete User Group	###############START###########
	#########	POST Delete User Group	#########
	public function postUserGroupDelete($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'tacacs user group',
			'action' => 'delete',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//
		//CHECK SHOULD I STOP THIS?//START//
		if( $this->shouldIStopThis() )
		{
			$data['error'] = $this->shouldIStopThis();
			return $res -> withStatus(400) -> write(json_encode($data));
		}
		//CHECK SHOULD I STOP THIS?//END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$usrGrp = TACUserGrps::where('id',$req->getParam('id'))->first();
		$data['result']=TACUserGrps::where('id',$req->getParam('id'))->delete();

		$data['changeConfiguration']=$this->changeConfigurationFlag(['unset' => 0]);

		$logEntry=array('action' => 'delete', 'obj_name' => $usrGrp->name, 'obj_id' => $req->getParam('id'), 'section' => 'tacacs user groups', 'message' => 404);
		$data['logging']=$this->APILoggingCtrl->makeLogEntry($logEntry);

		// $data['footprints']=TACUsers::where([['group','=',$req->getParam('id')]])->update(['group' => '0']);

		return $res -> withStatus(200) -> write(json_encode($data));
	}
########	Delete User	Group###############END###########
################################################
#########	POST CSV Device	#########
	public function postUserGroupCsv($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'user group',
			'action' => 'csv',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//
		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//
		$data['clear'] = shell_exec( TAC_ROOT_PATH . '/main.sh delete temp');
		$path = TAC_ROOT_PATH . '/temp/';
		$filename = 'tac_user_groups_'. $this->generateRandomString(8) .'.csv';

		$columns = $this->APICheckerCtrl->getTableTitles('tac_user_groups');

	  $f = fopen($path.$filename, 'w');
		$idList = $req->getParam('idList');
		$array = [];
		$array = ( empty($idList) ) ? TACUserGrps::select($columns)->get()->toArray() : TACUserGrps::select($columns)->whereIn('id', $idList)->get()->toArray();

		fputcsv($f, $columns /*, ',)'*/);
	  foreach ($array as $line) {
		fputcsv($f, $line /*, ',)'*/);
	  }

		$data['filename']=$filename;
		sleep(3);
		return $res -> withStatus(200) -> write(json_encode($data));
	}
########	CSV Device	###############END###########
################################################
########	User Group Datatables ###############START###########
	#########	POST User Group Datatables	#########
	public function postUserGroupDatatables($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'post',
			'object' => 'tacacs user group',
			'action' => 'datatables',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//

		unset($data['error']);//BEACAUSE DATATABLES USES THAT VARIABLE//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5, true))
		{
			$data['data'] = [];
			$data['recordsTotal'] = 0;
			$data['recordsFiltered'] = 0;
			return $res -> withStatus(200) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		$params=$req->getParams(); //Get ALL parameters form Datatables

		$columns = $this->APICheckerCtrl->getTableTitles('tac_user_groups'); //Array of all columnes that will used
		array_unshift( $columns, 'id' );
		array_push( $columns, 'created_at', 'updated_at' );
		$data['columns'] = $columns;
		$queries = (empty($params['searchTerm'])) ? [] : $params['searchTerm'];

		//Filter end
		$data['recordsTotal'] = TACUserGrps::count();
		//Get temp data for Datatables with Fliter and some other parameters
		$tempData = TACUserGrps::select($columns)->
		when( !empty($queries),
			function($query) use ($queries)
			{
				$query->where('name','LIKE', '%'.$queries.'%');
				return $query;
			});
		$data['recordsFiltered'] = $tempData->count();

		if (!empty($params['sortColumn']) and !empty($params['sortDirection']))
				$tempData = $tempData->orderBy($params['sortColumn'],$params['sortDirection']);

		$data['data'] = $tempData->get()->toArray();

		return $res -> withStatus(200) -> write(json_encode($data));
	}

########	User Group Datatables	###############END###########
################################################
########	List User Group	###############START###########
	#########	GET List User	Group#########
	public function getUserGroupList($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'tacacs user group',
			'action' => 'list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5, true))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//

		///IF GROUPID SET///
		if ($req->getParam('id') != null){
			$id = explode(',', $req->getParam('id'));

			$extra = '';
			if (!empty($req->getParam('extra'))) {
				$extra = json_decode($req->getParam('extra'));
				if (@$extra->type == 'user'){
					$data['extra'] = $extra;
					$data['results'] = TACUserGrps::leftJoin('tac_bind_usrGrp as ug', 'ug.group_id', '=', 'tac_user_groups.id')->
					select(['tac_user_groups.id as id','name AS text'])->orderBy('ug.order', 'asc')->
					where('ug.user_id', $extra->id)->get();
					return $res -> withStatus(200) -> write(json_encode($data));
				}
			}

			$data['results'] = TACUserGrps::select(['id','name AS text'])->whereIn('id', $id)->get();
			// if (  !count($data['results']) ) $data['results'] = null;
			return $res -> withStatus(200) -> write(json_encode($data));
		}
		//////////////////////
		////LIST OF GROUPS////
		$query = TACUserGrps::select(['id','name as text']);
		$data['total'] = $query->count();
		$search = $req->getParam('search');

		$query = $query->when( !empty($search), function($query) use ($search)
			{
				$query->where('name','LIKE', '%'.$search.'%');
			});

		$data['results']=$query->get();

		return $res -> withStatus(200) -> write(json_encode($data));

	}
########	List User Group	###############END###########
################################################
########	List LDAP Group	###############START###########
	#########	GET List LDAP	Group#########
	public function getLDAPGroupList($req,$res)
	{
		//INITIAL CODE////START//
		$data=array();
		$data=$this->initialData([
			'type' => 'get',
			'object' => 'tacacs ldap group',
			'action' => 'list',
		]);
		#check error#
		if ($_SESSION['error']['status']){
			$data['error']=$_SESSION['error'];
			return $res -> withStatus(401) -> write(json_encode($data));
		}
		//INITIAL CODE////END//

		//CHECK ACCESS TO THAT FUNCTION//START//
		if(!$this->checkAccess(5, true))
		{
			return $res -> withStatus(403) -> write(json_encode($data));
		}
		//CHECK ACCESS TO THAT FUNCTION//END//
		$ldap = MAVISLDAP::select()->first();

		$data['results'] = [ [
			'error' => true,
			'text' => '<p class="text-danger">Server Error!</p>',
			] ];

		if ( ! $ldap->enabled ){
			$data['results'][0]['text'] = '<p class="text-danger">LDAP Disabled!</p>';
			return $res -> withStatus(200) -> write(json_encode($data));
		}

		$username = ( strpos($ldap->user, '@') !== false ) ? $ldap->user : $ldap->user . '@'.( str_replace( ',', '.', preg_replace('/DC=/i', '', $ldap->base) ) );

		$username = ( $ldap->type == 'openldap' ) ? $ldap->user : $username;

		$config = [
			// Mandatory Configuration Options
			'hosts'            => array_map('trim', explode(',', $ldap->hosts) ),
			'base_dn'          => $ldap->base,
			'username'         => $username,
			'password'         => $ldap->password,
			// Optional Configuration Options
			'schema'           => ( $ldap->type == 'openldap' ) ?\Adldap\Schemas\OpenLDAP::class : \Adldap\Schemas\ActiveDirectory::class,
			'port'             => $ldap->port,
			'version'          => 3,
			'timeout'          => 5,
		];

		$ad = new Adldap();
		$ad->addProvider($config);

		try {
		    $provider = $ad->connect();
		} catch (ADErr $e) {
				$data['results'][0]['text'] = '<p class="text-danger">LDAP Connection Error!</p>';
				return $res -> withStatus(200) -> write(json_encode($data));
		}

		$adSearch = $provider->search();

		//$filter = 'CN=FileAccess_1';
		$search = $req->getParam('search');

		if ( $ldap->type == 'openldap' ) {
			$adGroups = ( empty($search) ) ? $adSearch->select()->where('objectclass', 'posixGroup')->limit(10)->get() : $adSearch->select()->where('objectclass', 'posixGroup')->where('cn', 'contains', $search)->limit(10)->get();

			$items = [];

			for ($i=0; $i < count($adGroups); $i++) {
				$items[] = array(
					'text' => (is_array($adGroups[$i]->cn)) ? $adGroups[$i]->cn[0] : $adGroups[$i]->cn,
					'cn' => (is_array($adGroups[$i]->cn)) ? $adGroups[$i]->cn[0] : $adGroups[$i]->cn,
					'dn' => (is_array($adGroups[$i]->dn)) ? $adGroups[$i]->dn[0] : $adGroups[$i]->dn,
					'id' => (is_array($adGroups[$i]->dn)) ? $adGroups[$i]->dn[0] : $adGroups[$i]->dn,
				);
			}
		} else {
			$adGroups = ( empty($search) ) ? $adSearch->groups()->select()->limit(10)->get() : $adSearch->groups()->select()->where('cn', 'contains', $search)->limit(10)->get();

			$items = [];

			for ($i=0; $i < count($adGroups); $i++) {
				$items[] = array(
					'text' => (is_array($adGroups[$i]->cn)) ? $adGroups[$i]->cn[0] : $adGroups[$i]->cn,
					'cn' => (is_array($adGroups[$i]->cn)) ? $adGroups[$i]->cn[0] : $adGroups[$i]->cn,
					'dn' => (is_array($adGroups[$i]->distinguishedname)) ? $adGroups[$i]->distinguishedname[0] : $adGroups[$i]->distinguishedname,
					'id' => (is_array($adGroups[$i]->distinguishedname)) ? $adGroups[$i]->distinguishedname[0] : $adGroups[$i]->distinguishedname,
				);
			}
		}

		$data['test1'] = $adGroups;
		$data['results'] = $items;

		// try {
		//     if ( ! $ad->auth()->attempt( $mavis->getUsername(), $mavis->getPassword() ) ) $mavis->out(AV_V_RESULT_FAIL);
		// } catch (Adldap\Auth\UsernameRequiredException $e) {
		//     // The user didn't supply a username.
		//     $mavis->out(AV_V_RESULT_FAIL);
		// } catch (Adldap\Auth\PasswordRequiredException $e) {
		//     // The user didn't supply a password.
		//     $mavis->out(AV_V_RESULT_FAIL);
		// }
		//
		// if ( ! $ad->auth()->attempt( $mavis->getUsername(), $mavis->getPassword() ) ) $mavis->out(AV_V_RESULT_FAIL);
		//
		// //$adUser = $search->rawFilter($filter)->get();
		//
		// $groupList = [];
		// for ($i=0; $i < count($adUser->memberof); $i++) {
		// 	preg_match_all('/^CN=(.*?),.*/s', $adUser->memberof[$i], $groupName);
		// 	$groupList[] = $groupName[1][0];
		// }

		//////////////////////
		////LIST OF GROUPS////
		// $data['incomplete_results'] = false;
		// $data['totalCount'] = TACUserGrps::select(['id','name'])->count();
		//
		// $take = 10 * $req->getParam('page');
		// $offset = 10 * ($req->getParam('page') - 1);
		// $data['take'] = $take;
		// $data['offset'] = $offset;
		// $tempData = TACUserGrps::select(['id','name','enable','message'])->
		// 	when( !empty($search), function($query) use ($search)
		// 	{
		// 		$query->where('name','LIKE', '%'.$search.'%');
		// 	})->
		// 	take($take)->
		// 	offset($offset);
		//
		// $tempCounter = $tempData->count();
		//
		// $tempData = $tempData->get()->toArray();
		// $data['pagination'] = (!$tempData OR $tempCounter < 10) ? ['more' => false] : [ 'more' => true];
		// $data['results']= ( $take == 10 AND empty($search) ) ? array( 0 => $noneItem) : array();
		// foreach($tempData as $group)
		// {
		// 	$group['text'] = $group['name'];
		// 	$group['message'] = ($group['message'] != '') ? true : false;
		// 	$group['enable'] = ($group['enable'] != '') ? true : false;
		// 	array_push($data['results'],$group);
		// }

		return $res -> withStatus(200) -> write(json_encode($data));
	}
########	List LDAP Group	###############END###########
################################################


}//END OF CLASS//
