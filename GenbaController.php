<?php
class GenbaController2 extends AppController {
	public $helpers = array ('Html','Form');
	public $uses = array('Site','SiteLog','Prefecture','User','Construction','Partner','Guarantee','Request','ConstructionRel','GuaranteeRel','RequestRel','Process','Tprocess','ConstructionRelToSite','Material','MaterialRel','TprocessToSite');

	function beforeFilter(){
		parent::beforeFilter();
	}
	
	function admin_list2($id=null) {
		 
		//delete検索の内容をクリアする
		if(empty($this->data) && empty($this->request->params['named']['page'])){
			$this->Session->delete('g_sid');
			$this->Session->delete('g_sname');
			$this->Session->delete('g_partner');
			$this->Session->delete('g_user');
			$this->Session->delete('g_prefecture');
			$this->Session->delete('g_address');
			$this->Session->delete('g_sdate');
			$this->Session->delete('g_edate_st');
			$this->Session->delete('g_edate_ed');
		}
		
		//データ初期化
		$this->getPrefecture();
	 	$this->getUser();
	 	$this->getPartner();
	 	$Process = $this->ProcessUpload();
	 	$this->set('process',$Process);
		$conditions =array();
		$fields =array('Site.*','DATE_ADD(Site.start_date, interval (Site.period+30) day) AS end_date');

		//POSTデータ
		$sid = isset($this->data['sid'])?$this->data['sid']:$id;//現場コード		
		$sname = isset($this->data['sname'])?$this->data['sname']:null;//現場名
		$partner = isset($this->data['partner'])?$this->data['partner']:null;//施工業者
		$user = isset($this->data['user'])?$this->data['user']:null;//現場監督
		$prefecture = isset($this->data['prefecture'])?$this->data['prefecture']:null;//都道府県
		$address = isset($this->data['address'])?$this->data['address']:null;//住所
		//$sdate = isset($this->data['sdate'])?$this->data['sdate']:null;//着工日
		
		
		
		$check = isset($this->data['check'])?$this->data['check']:null;// 施工ステータス
		$edate_st = isset($this->data['edate_st'])?$this->data['edate_st']:null;//完了目標
		$edate_ed = isset($this->data['edate_ed'])?$this->data['edate_ed']:null;//完了目標
		
		//検索の内容をセッションに入れる
		if($this->request->is('post')||$id){
			$this->Session->write('g_sid',$sid);
			$this->Session->write('g_sname',$sname);
			$this->Session->write('g_partner',$partner);
			$this->Session->write('g_user',$user);
			$this->Session->write('g_prefecture',$prefecture);
			$this->Session->write('g_address',$address);
			//$this->Session->write('g_sdate',$sdate);
			$this->Session->write('g_check',$check);
			$this->Session->write('g_edate_st',$edate_st);
			$this->Session->write('g_edate_ed',$edate_ed);
		}
		
		//検索の内容をリッセトする
		$sid=$this->Session->read('g_sid');
		$sname=$this->Session->read('g_sname');
		$partner=$this->Session->read('g_partner');
		$user=$this->Session->read('g_user');
		$prefecture=$this->Session->read('g_prefecture');
		$address=$this->Session->read('g_address');
// 		$sdate=$this->Session->read('g_sdate');
		$check = $this->Session->read('g_check');
		$edate_st =$this->Session->read('g_edate_st');
		$edate_ed =$this->Session->read('g_edate_ed');
		
		//検索条件生成
		if(!empty($sid)){
			$conditions['Site.id like'] = $sid ;
		}
		if(!empty($sname)){
			$conditions['Site.name like'] = '%'.str_replace(array('様','邸'),'',$sname).'%';
		}
		if(intval($user)>0){
			$conditions['Site.user_id'] = $user;
		}
		if(intval($prefecture)>0){
			$conditions['Site.prefecture'] = $prefecture;
		}
		if(!empty($address)){
			$conditions['or']['Site.address like'] = '%'.$address.'%';
		}
// 		if(!empty($sdate)){
// 			$conditions['Site.start_date'] = str_replace('/','-',$sdate);
// 		}
		
		if(!empty($check) && !empty($edate_st) && !empty($edate_ed)){
			//$conditions['DATE_ADD(start_date, interval (period+30) day) ='] = str_replace('/','-',$edate_st);
			$edate_st = str_replace('/', '-', $edate_st);
			$edate_ed = str_replace('/', '-', $edate_ed);
			
			$conditionsSubQuery = array('`Tprocess`.`process_id` IN ('. implode(',',$check) . ') ');
			$conditionsSubQuery['`Tprocess`.`due_date` >= '] = $edate_st;
			$conditionsSubQuery['`Tprocess`.`due_date` <= '] = $edate_ed;

			
			$db = $this->Tprocess->getDataSource();
			$subQuery = $db->buildStatement(
					array(
							'fields'     => array('`Tprocess`.`site_id`'),
							'table'      => $db->fullTableName($this->Tprocess),
							'alias'      => 'Tprocess',
							'limit'      => null,
							'offset'     => null,
							'joins'      => array(),
							'conditions' => $conditionsSubQuery,
							'order'      => null,
							'group'      => null
					),
					$this->Tprocess
			);
			$subQuery = ' `Site`.`id` IN (' . $subQuery . ') ';
			$subQueryExpression = $db->expression($subQuery);
			
			$conditions[] = $subQueryExpression;
		}
		
		//検索結果
		$conditions['Site.delete_flag']= 0;
		$conditions['Site.complete_flag']= 0;
		$this->paginate = array(
				'fields'=>$fields,
				'limit' =>$this->perpage,
				'order' => array('Site.id'=>'DESC') //表示順
		);
		$Site = $this->paginate('Site',$conditions);
		
		if(intval($partner)>0){
			$conditions['ConstructionRelToSite.partner_id'] = $partner;
			$Site = $this->paginate('ConstructionRelToSite',$conditions);
		}
		$g_partner = $this->Session->read('g_partner');
		//項目追記
		foreach ($Site as $k=>$v) {
			$Site[$k]['Site']['pnm'] = $this->getPrefectureNM($v['Site']['prefecture']);  //都道府県
			$tprocess = $this->Tprocess->query("SELECT * FROM t_process as Tprocess WHERE Tprocess.end_date IS NOT NULL and Tprocess.site_id=".$v['Site']['id']." ORDER BY Tprocess.process_id DESC LIMIT 1");
			
			$Site[$k]['Site']['process'] = empty($tprocess[0]['Tprocess']['process_id'])?$this->getProcessNM(1):$this->getProcessNM($tprocess[0]['Tprocess']['process_id']+1);//ステータス
			//pr($Site[$k]['Site']['process']);
			/*次のステータスの完了標に対して残り3
			以内になったらオレンジ背景。
			当以降になったら⾚背景。*/
			$date = NULL;
			if (!empty($tprocess[0]['Tprocess']['process_id']) && !empty($tprocess[0]['Tprocess']['due_date'])) {
				$date=floor((strtotime($tprocess[0]['Tprocess']['due_date']) - strtotime(date('Y-m-d')))/86400);
			}

			$Site[$k]['Site']['t_due_date'] = $date;
			if($g_partner){
				$Site[$k]['Site']['slcount'] = $this->getPartnerSiteLogCount($v['Site']['id'],$g_partner);
			}else{
				$Site[$k]['Site']['slcount'] = $this->getSiteLogCount($v['Site']['id']);      //件数表示
			}
		}
		$this->set('sites',$Site);

	}
	function admin_upload($sid=null,$hv = null){
		
		$concat_arr = explode('_', $hv);
		
		//ins
		$material['MaterialRel']['site_id']         = $sid;//現場ID
		$material['MaterialRel']['category_id']     = $concat_arr[0];//資料種別ID
		$material['MaterialRel']['remarks']         = $concat_arr[1];//Remarks
		$material['MaterialRel']['file_path']       = $concat_arr[2];//filepath

		$material['MaterialRel']['partner_id']      = $concat_arr[3];//業者ID
		$material['MaterialRel']['open_flag']       = $concat_arr[4];//施工業者へ公開か		
				 			
		$material['MaterialRel']['create_date']     = date('Y-m-d H:i:s');               //登録日時
		$material['MaterialRel']['create_user_id']  = $this->Session->read('user_id');   //登録ユーザーID
		$material['MaterialRel']['update_date']     = date('Y-m-d H:i:s');                              //新規時、ヌル
		$material['MaterialRel']['update_user_id']  = $this->Auth->user('id');     
		$this->MaterialRel->save($material);
		
		//maxid 
		$sql = "select max(id) as maxid from t_material_rel";
		$rr = $this->MaterialRel->query($sql);
		$maxid = $rr[0][0]['maxid'];

		
		//upload
		$uploaded_path = WWW_ROOT."upload".DS."genba".DS;
		$filetype =  substr(strrchr($_FILES["file"]["name"], '.'), 0);
		$filename = $maxid.$filetype;
		move_uploaded_file($_FILES["file"]["tmp_name"], $uploaded_path . $filename);
		
		//画像名更新
		$mate['MaterialRel']['id']           = $maxid;//id
		$mate['MaterialRel']['sys_filename'] = $filename;//画像名
		$this->MaterialRel->save($mate);
		
		//
		echo $maxid;
		
		exit;
	}
	function admin_delete(){
		$this->layout = 'ajax';
		$id = $_POST['id'];
		if(!empty($id)){
			$this->MaterialRel->delete($id);
		}
		exit;
	}
	 function admin_edit($sid){
	 		 	
	 	$this->set('test',$this->test);
	 	$this->getTprocess($sid);
	 	$this->helpers = array('Session');
	 	//
	 	if(!empty($sid)){
	 		
	 		$data = $this->Site->findById($sid);
			
	 		//施工業者情報を取得
	 		$d = $this->ConstructionRel->find('all',array('conditions'=>array('ConstructionRel.site_id'=>$sid,'delete_flag'=>'0')));
	 		//項目を追記
	 		foreach ($d as $k=>$v) {
	 			
	 			//業者名
	 			$r = $this->Partner->find('all',array('conditions'=>array('id'=>$v['ConstructionRel']['partner_id'],'delete_flag'=>0)));
	 			$d[$k]['ConstructionRel']['txt_partner'] = $r[0]['Partner']['company_name'];
	 			
	 			//工事対象名
	 			$r = $this->Construction->find('all',array('conditions'=>array('id'=>$v['ConstructionRel']['construction_id'],'delete_flag'=>0)));
	 			$d[$k]['ConstructionRel']['txt_construction'] = $r[0]['Construction']['name'];
	 			
	 			//hv
	 			$d[$k]['ConstructionRel']['hv'] = $v['ConstructionRel']['construction_id'].'_'.$v['ConstructionRel']['partner_id'].'_'.$v['ConstructionRel']['app_use_flag'];

	 		} 
	 		$this->set('constructions',$d);
	 		
	 		//資料項目
	 		$materials = $this->MaterialRel->find('all',array('conditions'=>array('MaterialRel.site_id'=>$sid)));
	 		foreach ($materials as $k=>$v) {
	 			if(!empty($v['MaterialRel']['category_id'])){
		 			$category_id = $v['MaterialRel']['category_id'];
		 			$ms = $this->Material->findById($category_id);
		 			$materials[$k]['MaterialRel']['name'] = $ms['Material']['name'];//種類名を追記
	 			}
	 			if(!empty($v['MaterialRel']['partner_id'])){
		 			$partner_id = $v['MaterialRel']['partner_id'];
		 			$ps = $this->Partner->findById($partner_id);
		 			$materials[$k]['MaterialRel']['company_name'] = $ps['Partner']['company_name'];//Partner名を追記
	 			}
	 		}
	 		$this->set('materials',$materials);
	 		 
	 		
	 		
	 		
	 		//保証
	 		$guarantees = $this->GuaranteeRel->find('all',array('conditions'=>array('GuaranteeRel.site_id'=>$sid)));
	 		foreach ($guarantees as $k=>$v) {
	 			$guarantee_id = $v['GuaranteeRel']['guarantee_id'];
	 			$s = $this->Guarantee->findById($guarantee_id);
	 			$guarantees[$k]['GuaranteeRel']['name'] = $s['Guarantee']['name'];//保証名前を追記
	 		}
	 		$this->set('guarantees',$guarantees);
	 		
	 		//申請
	 		$requests = $this->RequestRel->find('all',array('conditions'=>array('RequestRel.site_id'=>$sid)));
	 		foreach ($requests as $k=>$v) {
	 			$request_id = $v['RequestRel']['request_id'];
	 			$s = $this->Request->findById($request_id);
	 			$requests[$k]['RequestRel']['name'] = $s['Request']['name'];//保証名前を追記
	 		}
	 		$this->set('requests',$requests);
			
	 		//
	 		$this->set('sites',$data);
	 	}else{
	 		die('Param Error');
	 	}
	 	$this->getPrefecture();
	 	$this->getUser();
	 	$this->getConstruction();
	 	$this->getPartner();
	 	$this->getGuarantee();
	 	$this->Request();
	 	$this->getMaterial();
	 }
	 
	 
	 function admin_complete_change(){
	 	if (!empty($this->data)){
	 		if (isset($this->data['type']) && isset($this->data['sid'])){
	 			$data['id'] = $this->data['sid'];
	 			$data['complete_flag'] = 1;
	 			$this->Site->save($data);
	 			
	 			$this->redirect('/admin/genba/list');
	 		}
	 	}
	 	
	 	$this->render(false);
	 }
	 
	 
	 function admin_status($sid){
	 	
	 	if (!empty($this->data)){
			//ユーザー
	 		$user = $this->Auth->user();
	 		if (isset($this->data['all_up_post']) && $this->data['all_up_post'] == 'post') {
	 			if ($_FILES['file']['error'] != 4){
	 				$file = $_FILES["file"]["tmp_name"];
	 				$tmp_path = WWW_ROOT."upload".DS."genba".DS."temp_xml".DS.$user['id'];
					
	 				//ファイルをUTF-8に変換
	 				$contents = mb_convert_encoding(file_get_contents($file), 'UTF-8','SJIS-win');
	 				$contents = str_replace('encoding="Shift_JIS"', 'encoding="UTF-8"', $contents);
	 				$tmp_file =fopen($tmp_path.DS.$_FILES["file"]["name"],"a");
	 				fwrite($tmp_file,$contents);
	 				
	 				fclose($tmp_file);
	 				
	 				if (!is_dir($tmp_path) && !file_exists($tmp_path)) {
	 					mkdir($tmp_path, 0777);
	 					chmod($tmp_path, 0777);
	 				}
	 				
	 				//move_uploaded_file($file,$tmp_path. DS.$_FILES["file"]["name"]);
	 			
		 			$xml = simplexml_load_file($tmp_path. DS.$_FILES["file"]["name"]); 
		 			$charts = $xml->itemlist->item->chartlist->chart;
	// 	 			pr($charts);
		 			//$handle=fopen($file,"r");
	// 	 			pr($xml);die;
		 			foreach ($charts as $chart) {
		 				//$status_list[] = $data;
		 				//内容
		 				$name = $chart->text;
	
		 				//完了予定日
		 				$end_date = $chart->enddate;
		 				$conditions = array('name'=>$name);
						$process = $this->Process->find('first',compact('conditions'));
						if (!empty($process)) {
							$conditions = array('site_id'=>$sid,'process_id'=>$process['Process']['id']);
							
							$t_process = $this->Tprocess->find('first',compact('conditions'));
							$this->Tprocess->create();
							$tprocess_save = array();
							if (empty($t_process)) {
								//ステータスがないとき
								$tprocess_save['site_id'] = $sid;
								$tprocess_save['process_id'] = $process['Process']['id'];
								$tprocess_save['due_date'] = $end_date;
								$tprocess_save['create_date'] = date('Y-m-d H:i:s');
								$tprocess_save['create_user_id'] = $user['id'];
								
							} else {
								$tprocess_save['id'] = $t_process['Tprocess']['id'];
								$tprocess_save['create_date'] = $t_process['Tprocess']['create_date'];
								$tprocess_save['update_date'] = date('Y-m-d H:i:s');
								$tprocess_save['update_user_id'] = $user['id'];
								$tprocess_save['due_date'] = $end_date;
							}
							$this->Tprocess->save($tprocess_save);
						}
		 			}
// 	 			
	 			unlink($tmp_path. DS.$_FILES["file"]["name"]);
	 			
	 			}
	 		} else {
	 			$data = array();
	 			if (!empty($this->data['d_tid'])) {
	 				$data['id'] = $this->data['d_tid'];
	 			}
	 			$data['site_id'] = $sid;
	 			$data['process_id'] = $this->data['d_pid'];
	 			if (isset($this->data['d_due_date'])){
	 				$data['due_date'] = $this->data['d_due_date'];
	 			}
	 			
	 			$data['end_date'] = $this->data['d_end_date'];
	 			if (!empty($_FILES)){
	 				$data['check_sheet'] = $_FILES["file"]["name"];
	 			}

	 			$data['create_user_id'] = $user['id'];
	 			// 	 		pr($_FILES); die;
	 			$res = $this->Tprocess->save($data);
	 			
	 			if ($res && !empty($_FILES)) {
	 				$uploaded_path =  WWW_ROOT."upload".DS."genba".DS."check_sheet".DS.$res['Tprocess']['id'];
	 					
	 				if (is_dir($uploaded_path)){
	 					chmod($uploaded_path, 0777);
	 					$this->deldir($uploaded_path);
	 				}
	 			
	 					
	 				if (!is_dir($uploaded_path) && !file_exists($uploaded_path)) {
	 					mkdir($uploaded_path, 0777);
	 					chmod($uploaded_path, 0777);
	 				}
	 				move_uploaded_file($_FILES["file"]["tmp_name"], $uploaded_path . DS.$_FILES["file"]["name"]);
	 			}
	 		}

	 	}
	 	
	 	if(!empty($sid)){
	 		//現場情報
	 		$site = $this->Site->findById($sid);
	 		//住所
	 		$this->getPrefectureById($site['Site']['prefecture']);
	 		//現場監督
	 		$this->getUserById($site['Site']['user_id']);
	 		$this->set('site',$site);
	 		
			//施工ステータス
	 		$Tprocess = $this->getAllTprocess($sid);
	 		
	 		//再構築
	 		$tmp_Tprocess = array();
	 		foreach ($Tprocess as $value){
	 			$tmp_Tprocess[$value['Process']['id']] = $value['Tprocess'];
	 		}
	 		$Tprocess = $tmp_Tprocess;
// 	 		pr($Tprocess);
	 		$this->set('Tprocess',$Tprocess);
	 		//すべて
	 		$Process = $this->Process();
	 		//pr($Process);
	 		$this->set('Process',$Process);
// 	 		pr($site);
	 	}
	 }
	 
	 
	 //データ初期化
	 function admin_regist(){
	 	$this->getPrefecture();
	 	$this->getUser();
	 	$this->getConstruction();
	 	$this->getPartner();
	 	$this->getGuarantee();
	 	$this->Request();
	 	$this->getMaterial();
	 }
	 
	function admin_success() {
	}
	 
	function admin_save(){
		
	 	//echo '<pre>';print_r($this->data);exit;
	 	//新規か編集を判断
		$mode = $this->data['mode'];
		
		//編集時、削除処理 五つ
		if($mode == 'edit'){
			
			//現場IDを取得
			$sid = $this->data['sid'];
			if(empty($sid)){
				die('Fetal Error: SID NOT FOUND');	
			}
						
			//申請関連データの削除
			$requests = $this->RequestRel->find('all',array('conditions'=>array('RequestRel.site_id'=>$sid)));
			foreach ($requests as $v) {
				$rid = $v['RequestRel']['id'];
				$this->RequestRel->delete($rid);
			}
			
			//保証関連データの削除
			$guarantees = $this->GuaranteeRel->find('all',array('conditions'=>array('GuaranteeRel.site_id'=>$sid)));
			foreach ($guarantees as $v) {
				$gid = $v['GuaranteeRel']['id'];
				$this->GuaranteeRel->delete($gid);
			}
			
			//施工関連データの削除
			$constructions = $this->ConstructionRel->find('all',array('conditions'=>array('ConstructionRel.site_id'=>$sid)));
			foreach ($constructions as $v) {
				$cid = $v['ConstructionRel']['id'];
				$this->ConstructionRel->delete($cid);
			}
			
			//資料関連データの削除
			/*$materials = $this->MaterialRel->find('all',array('conditions'=>array('MaterialRel.site_id'=>$sid)));
			foreach ($materials as $v) {
				$mid = $v['MaterialRel']['id'];
				$this->MaterialRel->delete($mid);
			}*/
			
			//現場関連データの削除
			//$this->Site->delete($sid);
		}

		//データ登録処理(新規・編集)

	 		//現場データ準備
		 	$data['Site']['name']           = $this->data['Site']['name'];           //現場名
		 	$data['Site']['prefecture']     = $this->data['Site']['prefecture'];     //都道府県
		 	$data['Site']['address']        = $this->data['Site']['address'];        //住所
		 	$data['Site']['area']           = $this->data['Site']['area'];           //坪数
		 	$data['Site']['period']         = $this->data['Site']['area'] * 3 + 30;  //工期（坪数/10*3+30）
		 	$data['Site']['notes']          = $this->data['Site']['notes'];          //注意事項
		 	$data['Site']['start_date']     = $this->data['Site']['start_date'];     //着工日
		 	$data['Site']['user_id']        = $this->data['Site']['user_id'];        //現場監督ID
		 	$data['Site']['audience_flag']  = $this->data['Site']['audience_flag'];  //内覧会実施・許諾
		 	$data['Site']['key_number']     = $this->data['Site']['key_number'];     //鍵番号
		 	$data['Site']['remarks']        = $this->data['Site']['remarks'];        //備考
		 	$data['Site']['delete_flag']    = 0;                                     //0 : 未削除
		 	$data['Site']['validity_flag']  = 0;                                     //有効性フラグ（0=有効,1=無効:ログイン不可）
		 	$data['Site']['create_date']    = date('Y-m-d H:i:s');                   //登録日時
		 	$data['Site']['create_user_id'] = $this->Session->read('user_id');       //登録ユーザーID
		 	$data['Site']['update_date']    = null;                                  //新規時、ヌル
		 	$data['Site']['update_user_id'] = null;                                  //新規時、ヌル
		 	
		 	//申請データ準備
		 	$request_num = $this->data['request_num'];
		 	
		 	//保証データ準備
		 	$guarantee_num = $this->data['guarantee_num'];

		 	//施工業者データ準備
		 	$construction_num = $this->data['RowCounter'];

		 	//資料登録データ準備
		 	//$material_num = $this->data['_RowCounter'];
		 	
		 	//編集
			if($mode == 'edit'){
			 	$data['Site']['name']           = "'".$this->data['Site']['name']."'";           //現場名
			 	$data['Site']['prefecture']     = "'".$this->data['Site']['prefecture']."'";    //都道府県
			 	$data['Site']['address']        = "'".$this->data['Site']['address']."'";       //住所
			 	$data['Site']['area']           = "'".$this->data['Site']['area']."'";           //坪数
			 	$data['Site']['period']         = "'".$data['Site']['period']."'";  		//工期（坪数/10*3+30）
			 	$data['Site']['notes']          = "'".$this->data['Site']['notes']."'";          //注意事項
			 	$data['Site']['start_date']     = "'".$this->data['Site']['start_date']."'";     //着工日
			 	$data['Site']['user_id']        = $this->data['Site']['user_id']		;        //現場監督ID
			 	$data['Site']['audience_flag']  = "'".$this->data['Site']['audience_flag']."'";  //内覧会実施・許諾
			 	$data['Site']['key_number']     = "'".$this->data['Site']['key_number']."'";     //鍵番号
			 	$data['Site']['remarks']        = "'".$this->data['Site']['remarks']."'";        //備考
			 	$data['Site']['delete_flag']    = 0;                                     		//0 : 未削除
			 	$data['Site']['validity_flag']  = 0;                                     		//有効性フラグ（0=有効,1=無効:ログイン不可）
			    $data['Site']['create_date']    = "'".$data['Site']['create_date']."'" ;   		//登録日時
			 	$data['Site']['update_date']    =  "'".date('Y-m-d H:i:s')."'";          		//新規時、ヌル
			 	$data['Site']['update_user_id'] =  $this->Session->read('user_id');      		//updateユーザーID
			 	
			 	$condition = array('id'=>$sid);
				if ($this->Site->updateAll($data['Site'],$condition)){
		 		
		 		//登録した現場IDを抽出
		 		$site_id = $sid;
		 		
		 		//施工業者登録
			 	if( $construction_num > 0 ){
			 		
			 		for ($i=0;$i<$construction_num;$i++){
			 			$this->ConstructionRel->create(false);
			 			if(!empty($this->data['cons'][$i])){
				 			$hv = $this->data['cons'][$i];
			 				$arr = explode('_', $hv);
				 			$construction['ConstructionRel']['site_id']         = $site_id;
				 			$construction['ConstructionRel']['construction_id'] = $arr[0];
				 			$construction['ConstructionRel']['partner_id']      = $arr[1];
				 			$construction['ConstructionRel']['app_use_flag']    = $arr[2];
				 			$construction['ConstructionRel']['create_date']     = date('Y-m-d H:i:s');               //登録日時
						 	$construction['ConstructionRel']['create_user_id']  = $this->Session->read('user_id');   //登録ユーザーID
						 	$construction['ConstructionRel']['update_date']     = null;                              //新規時、ヌル
						 	$construction['ConstructionRel']['update_user_id']  = null;     
				 			$this->ConstructionRel->save($construction);
			 			}
			 		}
			 	}
			 	//資料登録
			 	/*if( $material_num > 0 ){
			 		for ($i=0;$i<$material_num;$i++){
			 			$this->MaterialRel->create(false);
			 			if(!empty($this->data['mate'][$i])){
				 			$mhv = $this->data['mate'][$i];
				 			$marr = explode('_', $mhv);
				 			$material['MaterialRel']['site_id']         = $site_id;
				 			$material['MaterialRel']['category_id']     = $marr[0];
				 			$material['MaterialRel']['remarks']         = $marr[1];
				 			$material['MaterialRel']['file_path']       = $marr[2];
				 			//
				 			$filetype =  substr(strrchr($marr[2], '.'), 0);
				 			$material['MaterialRel']['sys_filename']   =  $site_id.'_'.($i+1).$filetype;
				 			//
				 			$material['MaterialRel']['partner_id']      = $marr[3];
				 			$material['MaterialRel']['open_flag']       = $marr[4];		
				 			
				 			$material['MaterialRel']['create_date']     = date('Y-m-d H:i:s');               //登録日時
						 	$material['MaterialRel']['create_user_id']  = $this->Session->read('user_id');   //登録ユーザーID
						 	$material['MaterialRel']['update_date']     = null;                              //新規時、ヌル
						 	$material['MaterialRel']['update_user_id']  = null;     
				 			$this->MaterialRel->save($material);
			 			}
			 		}
			 	}*/
			 	//保証登録
			 	for ($i=0;$i<$guarantee_num;$i++){
			 		$this->GuaranteeRel->create(false);
			 		$guarantee['GuaranteeRel']['site_id'] = $site_id;
			 		$guarantee['GuaranteeRel']['guarantee_id'] = $this->data['guarantee_'.$i];
			 		$guarantee['GuaranteeRel']['use_flag'] = $this->data['r_guarantee_'.$i];
			 		if(!empty($this->data['chk_'.$i])){
			 			$guarantee['GuaranteeRel']['submission_flag'] = 1;
			 		}else{
			 			$guarantee['GuaranteeRel']['submission_flag'] = 0;
			 		}
			 		$guarantee['GuaranteeRel']['create_date']    = date('Y-m-d H:i:s');               //登録日時
				 	$guarantee['GuaranteeRel']['create_user_id'] = $this->Session->read('user_id');   //登録ユーザーID
				 	$guarantee['GuaranteeRel']['update_date']    = null;                              //新規時、ヌル
				 	$guarantee['GuaranteeRel']['update_user_id'] = null;                              //新規時、ヌル
				 	$this->GuaranteeRel->save($guarantee);
			 	}
			 	
			 	//申請登録
		 		for ($i=0;$i<$request_num;$i++){
			 		$this->RequestRel->create(false);
			 		$request['RequestRel']['site_id'] = $site_id;
			 		$request['RequestRel']['request_id'] = $this->data['request_'.$i];
			 		$request['RequestRel']['use_flag'] = $this->data['r_request_'.$i];
			 		if(!empty($this->data['chkd_'.$i])){
			 			$request['RequestRel']['submission_flag'] = 1;
			 		}else{
			 			$request['RequestRel']['submission_flag'] = 0;
			 		}
			 		$request['RequestRel']['create_date']    = date('Y-m-d H:i:s');               //登録日時
				 	$request['RequestRel']['create_user_id'] = $this->Session->read('user_id');   //登録ユーザーID
				 	$request['RequestRel']['update_date']    = null;                              //新規時、ヌル
				 	$request['RequestRel']['update_user_id'] = null;                              //新規時、ヌル
				 	$this->RequestRel->save($request);
			 	}  
			 	//ステータス登録
		/*	 	$tp = $this->Tprocess->find('all',array('conditions'=>array('Tprocess.site_id'=>$site_id,'Tprocess.process_id'=>1)));
			 	if($this->data['Site']['start_date']!=$tp['Tprocess']['due_date']){
	 			$Process = $this->Process();
			 	$start_date = $this->data['Site']['start_date'];
			 	$area = $this->data['Site']['area']; 
			 	$count_day1 = ceil(($this->data['Site']['period']-30)/3)+60;
			 	$count_day2 = ceil(($this->data['Site']['period']-30)/3)+61;
			 	$count_day3 = ceil(($this->data['Site']['period']-30)/3)+65;
			 	$count_day4 = ceil(($this->data['Site']['period']-30)/3)+66;
			 	$count_day5 = $this->data['Site']['period']-5;
			 	$count_day6 = $this->data['Site']['period'];
			 	$count_day7 = $this->data['Site']['period']+1;
			 	$count_day8 = $this->data['Site']['period']+7;
			 	if($this->data['Site']['audience_flag']){
			 		$count_day9 = $this->data['Site']['period']+7;
			 	}else{
			 		$count_day9 = $this->data['Site']['period']+14;	
			 	}
			 	$date=array('1'=>$start_date,
			 				'2'=>$start_date,
			 				'3'=>date("Y-m-d",strtotime("$start_date +14 day")),
			 				'4'=>date("Y-m-d",strtotime("$start_date +24 day")),
			 				'5'=>date("Y-m-d",strtotime("$start_date +30 day")),
			 				'6'=>date("Y-m-d",strtotime("$start_date +37 day")),
			 				'7'=>date("Y-m-d",strtotime("$start_date +38 day")),
			 				'8'=>date("Y-m-d",strtotime("$start_date +50 day")),
			 				'9'=>date("Y-m-d",strtotime("$start_date +51 day")),
			 				'10'=>date("Y-m-d",strtotime("$start_date +55 day")),
			 				'11'=>date("Y-m-d",strtotime("$start_date +60 day")),
			 				'12'=>date("Y-m-d",strtotime("$start_date +$count_day1 day")),
			 				'13'=>date("Y-m-d",strtotime("$start_date +$count_day2 day")),
			 				'14'=>date("Y-m-d",strtotime("$start_date +$count_day3 day")),
			 				'15'=>date("Y-m-d",strtotime("$start_date +$count_day4 day")),
			 				'16'=>date("Y-m-d",strtotime("$start_date +$count_day5 day")),
			 				'17'=>date("Y-m-d",strtotime("$start_date +$count_day6 day")),
			 				'18'=>date("Y-m-d",strtotime("$start_date +$count_day7 day")),
			 				'19'=>date("Y-m-d",strtotime("$start_date +$count_day8 day")),
			 				'20'=>date("Y-m-d",strtotime("$start_date +$count_day9 day")),
			 	
			 	);
		 		foreach($Process as $v){
			 		$this->Tprocess->create(false);
			 		$tprocess['Tprocess']['site_id'] = $site_id;
			 		$tprocess['Tprocess']['process_id'] = $v['Process']['id'];//ステータスID
			 		$tprocess['Tprocess']['due_date'] = $date[$v['Process']['id']];//完了予定日
			 		
			 		$tprocess['Tprocess']['create_date']    = date('Y-m-d H:i:s');               //登録日時
				 	$tprocess['Tprocess']['create_user_id'] = $this->Session->read('user_id');   //登録ユーザーID
				 	$tprocess['Tprocess']['update_date']    = null;                              //新規時、ヌル
				 	$tprocess['Tprocess']['update_user_id'] = null;                              //新規時、ヌル
				 	
				 	$this->Tprocess->updateAll($data['Site'],array('process_id'=>$v['Process']['id']));
			 	}     
			 	}     */
		 		
		 		//画面遷移
		 		$this->redirect(array('controller'=>'genba','action'=>'success'));
		 	}
		 	}else{
		 	if ($this->Site->save($data)){
		 		
		 		//登録した現場IDを抽出
		 		$sites = $this->Site->find('all',array('order'=>array('id'=>'desc'),'limit'=>1));
		 		$site_id = $sites[0]['Site']['id'];
		 		
		 		//施工業者登録
			 	if( $construction_num > 0 ){
			 		
			 		for ($i=0;$i<$construction_num;$i++){
			 			$this->ConstructionRel->create(false);
			 			if(!empty($this->data['cons'][$i])){
				 			$hv = $this->data['cons'][$i];
			 				$arr = explode('_', $hv);
				 			$construction['ConstructionRel']['site_id']         = $site_id;
				 			$construction['ConstructionRel']['construction_id'] = $arr[0];
				 			$construction['ConstructionRel']['partner_id']      = $arr[1];
				 			$construction['ConstructionRel']['app_use_flag']    = $arr[2];
				 			$construction['ConstructionRel']['create_date']     = date('Y-m-d H:i:s');               //登録日時
						 	$construction['ConstructionRel']['create_user_id']  = $this->Session->read('user_id');   //登録ユーザーID
						 	$construction['ConstructionRel']['update_date']     = null;                              //新規時、ヌル
						 	$construction['ConstructionRel']['update_user_id']  = null;     
				 			$this->ConstructionRel->save($construction);
			 			}
			 		}
			 	}
			 	//資料登録
			 	if( $material_num > 0 ){
			 		for ($i=0;$i<$material_num;$i++){
			 			$this->MaterialRel->create(false);
			 			if(!empty($this->data['mate'][$i])){
				 			$mhv = $this->data['mate'][$i];
				 			$marr = explode('_', $mhv);
				 			$material['MaterialRel']['site_id']         = $site_id;
				 			$material['MaterialRel']['category_id']     = $marr[0];
				 			$material['MaterialRel']['remarks']         = $marr[1];
				 			$material['MaterialRel']['file_path']       = $marr[2];
				 			$material['MaterialRel']['partner_id']      = $marr[3];
				 			$material['MaterialRel']['open_flag']       = $marr[4];		
				 			
				 			$material['MaterialRel']['create_date']     = date('Y-m-d H:i:s');               //登録日時
						 	$material['MaterialRel']['create_user_id']  = $this->Session->read('user_id');   //登録ユーザーID
						 	$material['MaterialRel']['update_date']     = null;                              //新規時、ヌル
						 	$material['MaterialRel']['update_user_id']  = null;     
				 			$this->MaterialRel->save($material);
			 			}
			 		}
			 	}
			 	//保証登録
			 	for ($i=0;$i<$guarantee_num;$i++){
			 		$this->GuaranteeRel->create(false);
			 		$guarantee['GuaranteeRel']['site_id'] = $site_id;
			 		$guarantee['GuaranteeRel']['guarantee_id'] = $this->data['guarantee_'.$i];
			 		$guarantee['GuaranteeRel']['use_flag'] = $this->data['r_guarantee_'.$i];
			 		if(!empty($this->data['chk_'.$i])){
			 			$guarantee['GuaranteeRel']['submission_flag'] = 1;
			 		}else{
			 			$guarantee['GuaranteeRel']['submission_flag'] = 0;
			 		}
			 		$guarantee['GuaranteeRel']['create_date']    = date('Y-m-d H:i:s');               //登録日時
				 	$guarantee['GuaranteeRel']['create_user_id'] = $this->Session->read('user_id');   //登録ユーザーID
				 	$guarantee['GuaranteeRel']['update_date']    = null;                              //新規時、ヌル
				 	$guarantee['GuaranteeRel']['update_user_id'] = null;                              //新規時、ヌル
				 	$this->GuaranteeRel->save($guarantee);
			 	}
			 	
			 	//申請登録
		 		for ($i=0;$i<$request_num;$i++){
			 		$this->RequestRel->create(false);
			 		$request['RequestRel']['site_id'] = $site_id;
			 		$request['RequestRel']['request_id'] = $this->data['request_'.$i];
			 		$request['RequestRel']['use_flag'] = $this->data['r_request_'.$i];
			 		if(!empty($this->data['chkd_'.$i])){
			 			$request['RequestRel']['submission_flag'] = 1;
			 		}else{
			 			$request['RequestRel']['submission_flag'] = 0;
			 		}
			 		$request['RequestRel']['create_date']    = date('Y-m-d H:i:s');               //登録日時
				 	$request['RequestRel']['create_user_id'] = $this->Session->read('user_id');   //登録ユーザーID
				 	$request['RequestRel']['update_date']    = null;                              //新規時、ヌル
				 	$request['RequestRel']['update_user_id'] = null;                              //新規時、ヌル
				 	$this->RequestRel->save($request);
			 	}
			 	//ステータス登録
// 	 			$Process = $this->Process();
// 			 	$start_date = $this->data['Site']['start_date'];
// 			 	$area = $this->data['Site']['area']; 
// 			 	$count_day1 = $area+60;
// 			 	$count_day2 = $area+61;
// 			 	$count_day3 = $area+65;
// 			 	$count_day4 = $area+66;
// 			 	$count_day5 = ($area*3)+25;
// 			 	$count_day6 = ($area*3)+30;
// 			 	$count_day7 = ($area*3)+31;
// 			 	$count_day8 = ($area*3)+37;
// 			 	if($this->data['Site']['audience_flag']){
// 			 		$count_day9 = $area*3+37;
// 			 	}else{
// 			 		$count_day9 = $area*3+44;	
// 			 	}
// 			 	$date=array('1'=>$start_date,
// 			 				'2'=>$start_date,
// 			 				'3'=>date("Y-m-d",strtotime("$start_date +14 day")),
// 			 				'4'=>date("Y-m-d",strtotime("$start_date +24 day")),
// 			 				'5'=>date("Y-m-d",strtotime("$start_date +30 day")),
// 			 				'6'=>date("Y-m-d",strtotime("$start_date +37 day")),
// 			 				'7'=>date("Y-m-d",strtotime("$start_date +38 day")),
// 			 				'8'=>date("Y-m-d",strtotime("$start_date +50 day")),
// 			 				'9'=>date("Y-m-d",strtotime("$start_date +51 day")),
// 			 				'10'=>date("Y-m-d",strtotime("$start_date +55 day")),
// 			 				'11'=>date("Y-m-d",strtotime("$start_date +60 day")),
// 			 				'12'=>date("Y-m-d",strtotime("$start_date +$count_day1 day")),
// 			 				'13'=>date("Y-m-d",strtotime("$start_date +$count_day2 day")),
// 			 				'14'=>date("Y-m-d",strtotime("$start_date +$count_day3 day")),
// 			 				'15'=>date("Y-m-d",strtotime("$start_date +$count_day4 day")),
// 			 				'16'=>date("Y-m-d",strtotime("$start_date +$count_day5 day")),
// 			 				'17'=>date("Y-m-d",strtotime("$start_date +$count_day6 day")),
// 			 				'18'=>date("Y-m-d",strtotime("$start_date +$count_day7 day")),
// 			 				'19'=>date("Y-m-d",strtotime("$start_date +$count_day8 day")),
// 			 				'20'=>date("Y-m-d",strtotime("$start_date +$count_day9 day")),
			 	
// 			 	);
// 		 		foreach($Process as $v){
// 			 		$this->Tprocess->create(false);
// 			 		$tprocess['Tprocess']['site_id'] = $site_id;
// 			 		$tprocess['Tprocess']['process_id'] = $v['Process']['id'];//ステータスID
// 			 		$tprocess['Tprocess']['due_date'] = $date[$v['Process']['id']];//完了予定日
			 		
// 			 		$tprocess['Tprocess']['create_date']    = date('Y-m-d H:i:s');               //登録日時
// 				 	$tprocess['Tprocess']['create_user_id'] = $this->Session->read('user_id');   //登録ユーザーID
// 				 	$tprocess['Tprocess']['update_date']    = null;                              //新規時、ヌル
// 				 	$tprocess['Tprocess']['update_user_id'] = null;                              //新規時、ヌル
// 				 	$this->Tprocess->save($tprocess);
// 			 	}
		 		
		 		//画面遷移
		 		$this->redirect(array('controller'=>'genba','action'=>'success'));
		 	}
		 	}
	    //}else{
	    	//編集時登録
	    	
	    	//echo '<pre>';print_r($dataSource);exit;
	    //}

	 }
	//入室情報：件数表示
	 function getSiteLogCount($sid){
	 	$d = $this->SiteLog->find('all', array('conditions'=>array('SiteLog.site_id'=>$sid,'SiteLog.delete_flag'=>'0')));
	 	return count($d);	 	
	 }
	 function getPartnerSiteLogCount($sid,$g_partner){
	 	$d = $this->SiteLog->find('all',array('conditions'=>array('SiteLog.partner_id'=>$g_partner,'SiteLog.site_id'=>$sid)));
	 	return count($d);	 	
	 }
	//prefecture id より nameを取得する
	 function getPrefectureNM($pid){
	 	$prefecture = $this->Prefecture->findById($pid);
	 	 return $prefecture['Prefecture']['name'] ;	 	
	 }
	 //住所
	 function getPrefecture(){
	 	$prefecture = $this->Prefecture->find('all',array('conditions'=>array('Prefecture.delete_flag'=>0),'order'=>array('Prefecture.sort'=>'ASC')));
	 	$this->set('prefecture',$prefecture);	 	
	 }
	 //住所
	 function getPrefectureById($id){
	 	$prefecture = $this->Prefecture->find('first',array('conditions'=>array('Prefecture.delete_flag'=>0,'Prefecture.id'=>$id)));
	 	$this->set('prefecture',$prefecture);	 	
	 }
	 //現場監督
	 function getUser(){
	 	$user = $this->User->find('all',array('conditions'=>array('User.type'=>2,'User.delete_flag'=>0)));
	 	$this->set('user',$user); 	
	 } 
	 //現場監督
	 function getUserById($id){
	 	$user = $this->User->find('first',array('conditions'=>array('User.type'=>2,'User.delete_flag'=>0,'User.id'=>$id)));
	 	$this->set('user',$user); 	
	 } 
	 //対象工事
	 function getConstruction(){
	 	$construction = $this->Construction->find('all',array('conditions'=>array('Construction.delete_flag'=>0),'order'=>array('Construction.sort'=>'ASC')));
	 	$this->set('construction',$construction); 
	 }
	 // 施工業者
	 function getPartner(){
	 	$partner = $this->Partner->find('all',array('conditions'=>array('Partner.delete_flag'=>0,'Partner.admin_flag'=>0)));
	 	$this->set('partner',$partner); 	
	 }
	  
	 //保証
	function getGuarantee(){
		$guarantee = $this->Guarantee->find('all',array('conditions'=>array('Guarantee.select_flag'=>1,'Guarantee.delete_flag'=>0),'order'=>'Guarantee.sort'));
		$this->set('guarantee',$guarantee); 	
	}
	//申請
	function Request(){
		$request = $this->Request->find('all',array('conditions'=>array('Request.select_flag'=>1,'Request.delete_flag'=>0),'order'=>'Request.sort'));
		$this->set('request',$request); 	
	}
	//ステータス
	function Process(){
		$process = $this->Process->find('all',array('conditions'=>array('Process.delete_flag'=>0),'order'=>'Process.sort'));
		return $process;
	}
	//ステータス
	function ProcessUpload(){
		$process = $this->Process->find('all',array('conditions'=>array('Process.delete_flag'=>0,'Process.upload_flag'=>1),'order'=>'Process.sort'));
		return $process;
	}
	//資料
	function getMaterial(){
		$material = $this->Material->find('all',array('conditions'=>array('Material.delete_flag'=>0),'order'=>'Material.sort'));
		$this->set('material',$material); 	
	}
	//施工ステータス
	function getTprocess($sid){
		$Tprocess = $this->Tprocess->query("SELECT * FROM t_process AS Tprocess LEFT JOIN m_process AS `Process` ON Tprocess.process_id=`Process`.id WHERE Tprocess.site_id=".$sid);	
		$this->set('Tprocess',$Tprocess); 	
	} 
	
	//施工ステータス
	function getAllTprocess($sid){
		$Tprocess = $this->Tprocess->find('all',array('conditions'=>array('Tprocess.site_id'=>$sid),'order'=>'Process.sort'));
		//$this->set('Tprocess',$Tprocess);
		return $Tprocess;
	}
	//prefecture id より nameを取得する
	 function getProcessNM($pid){
	 	$process = $this->Process->findById($pid);
	 	if(!empty($process)){
	 	 return $process['Process']['name'] ;	
	 	} else{
	 		$process = $this->Process->findById($pid-1);
	 		if(!empty($process)){
	 			return $process['Process']['name'] ;
	 		}
	 	}
	 } 
	 
	 
	 //delete dir
	 private function deldir($dir) {
	 	if (!is_dir($dir) && !file_exists($dir)){
	 		return;
	 	}
	 	$dh=opendir($dir);
	 	while ($file=readdir($dh)) {
	 		if($file!="." && $file!="..") {
	 			$fullpath=$dir.DS.$file;
	 			if(!is_dir($fullpath)) {
	 				unlink($fullpath);
	 			} else {
	 				$this->deldir($fullpath);
	 			}
	 		}
	 	}
	 
	 	closedir($dh);
	 	//delete dir
	 	if(rmdir($dir)) {
	 		return true;
	 	} else {
	 		return false;
	 	}
	 }
}
?>