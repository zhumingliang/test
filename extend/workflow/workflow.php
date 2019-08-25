<?php
/**
* 工作流类库
*/
namespace workflow;

define ( 'BEASE_URL', realpath ( dirname ( __FILE__ ) ) );

//配置文件
require_once BEASE_URL . '/config/config.php';
//数据库操作
require_once BEASE_URL . '/db/InfoDb.php';
require_once BEASE_URL . '/db/FlowDb.php';
require_once BEASE_URL . '/db/ProcessDb.php';
require_once BEASE_URL . '/db/LogDb.php';
require_once BEASE_URL . '/db/UserDb.php';
//类库
require_once BEASE_URL . '/class/TaskService.php';
//配置全局类
//消息
require_once BEASE_URL . '/msg/mail.php';


	/**
	 * 根据单据ID获取流程信息
	 */
	class workflow{
		/**
		 * 根据业务类别获取工作流
		 *
		 * @param  $type 类别
		 */
		function getWorkFlow($type)
		{
			return FlowDb::getWorkflowByType($type);
		}
		/**
		 *流程发起
		 *
		 * @param  $config 参数信息
		 * @param  $uid    用户ID
		 **/
		function startworkflow($config,$uid)
		{
			$wf_id = $config['wf_id'];//工作流id
			$wf_fid = $config['wf_fid'];//走流程的申请id
			$wf_type = $config['wf_type'];//流程列别：
			//判断流程是否存在
			$wf = FlowDb::getWorkflow($wf_id);
			if(!$wf){
				return ['msg'=>'未找到工作流！','code'=>'-1'];
			}
			//判断单据是否存在
			/*$wf = InfoDB::getbill($wf_fid,$wf_type);
			if(!$wf){
				return ['msg'=>'单据不存在！','code'=>'-1'];
			}*/
			
			//根据流程获取流程第一个步骤
			$wf_process = ProcessDb::getWorkflowProcess($wf_id);
			if(!$wf_process){
				return ['msg'=>'流程设计出错，未找到第一步流程，请联系管理员！','code'=>'-1'];
			}
			//满足要求，发起流程
			$wf_run = InfoDB::addWorkflowRun($wf_id,$wf_process['id'],$wf_fid,$wf_type,$uid);
			if(!$wf_run){
				return ['msg'=>'流程发起失败，数据库操作错误！！','code'=>'-1'];
			}
			//添加流程步骤日志
			$wf_process_log = InfoDB::addWorkflowProcess($wf_id,$wf_process,$wf_run,$uid);
			if(!$wf_process_log){
				return ['msg'=>'流程步骤操作记录失败，数据库错误！！！','code'=>'-1'];
			}
			//添加流程日志
			$run_cache = InfoDB::addWorkflowCache($wf_run,$wf,$wf_process,$wf_fid);
			if(!$run_cache){
				return ['msg'=>'流程步骤操作记录失败，数据库错误！！！','code'=>'-1'];
			}
			
			//更新单据状态
			$bill_update = InfoDB::UpdateBill($wf_fid,$wf_type);
			if(!$bill_update){
				return ['msg'=>'流程步骤操作记录失败，数据库错误！！！','code'=>'-1'];
			}
			
			$run_log = LogDb::AddrunLog($uid,$wf_run,$config,'Send');
			
			return ['run_id'=>$wf_run,'msg'=>'success','code'=>'1'];
		}

        /**
         * 流程状态查询
         * @param $wf_fid
         * @param $wf_type
         * @return array
         * @throws \think\Exception
         */
		function workflowInfo($wf_fid,$wf_type)
		{
			if ($wf_fid == '' || $wf_type == '') {
				return ['msg'=>'单据编号，单据表不可为空！','code'=>'-1'];
			}
			$wf = InfoDB::workflowInfo($wf_fid,$wf_type);
			return $wf;
		}


        /**
         * 流程状态查询
         * @param $wf_fid
         * @param $wf_type
         * @return array
         * @throws \think\Exception
         */
        function workflowInfoForComplete($wf_fid,$wf_type)
        {
            if ($wf_fid == '' || $wf_type == '') {
                return ['msg'=>'单据编号，单据表不可为空！','code'=>'-1'];
            }
            $wf = InfoDB::workflowInfoForComplete($wf_fid,$wf_type);
            return $wf;
        }

		/*
		 * 获取下一步骤信息
		 *
		 * @param  $config 参数信息
		 * @param  $uid 用户ID
		 **/
		function workdoaction($config,$uid)
		{
			if( @$config['run_id']=='' || @$config['run_process']==''){
		       	throw new \Exception ( "config参数信息不全！" );
			}
			$taskService = new TaskService();//工作流服务
			$wf_actionid = $config['submit_to_save'];
			$sing_st = $config['sing_st'];
			if($sing_st == 0){
				if ($wf_actionid == "ok") {//提交处理
					$ret = $taskService->doTask($config,$uid);
				} else if ($wf_actionid == "back") {//退回处理
					$ret = $taskService->doBack($config,$uid);
				} else if ($wf_actionid == "sing") {//会签
					$ret = $taskService->doSing($config,$uid);
				} else { //通过
					throw new \Exception ( "参数出错！" );
				}
			}else{
				$ret = $taskService->doSingEnt($config,$uid,$wf_actionid);
			}
			return $ret;
		}
		/*
		 * 工作流监控
		 *
		 * @param  $status 流程状态
		 **/
		function worklist($status = 0)
		{
			return InfoDB::worklist();
		}
		function getprocessinfo($pid,$run_id){
			
			if( @$pid=='' || @$run_id ==''){
		       	throw new \Exception ( "config参数信息不全！" );
			}
			$wf_process = ProcessDb::Getrunprocess($pid,$run_id);
			if($wf_process['auto_person']==3){
				$todo = $wf_process['sponsor_ids'].'*%*'.$wf_process['sponsor_text'];
				}else{
				$todo = '';
			}
			return $todo;
		}
		
		function send_mail()
		{
			$mail = new SendMail();
			$mail->setServer("smtp.qq.com", "1838188896@qq.com", "pass");
			$mail->setFrom("1838188896@qq.com");
			$mail->setReceiver("632522043@qq.com");
			$mail->setReceiver("632522043@qq.com");
			$mail->setMailInfo("test", "<b>test</b>");
			$mail->sendMail();
		}
		
}