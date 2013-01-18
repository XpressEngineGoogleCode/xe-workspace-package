<?php
    /**
     * @class project 
     * @author NHN (developers@xpressengine.com)
     * @brief  project package
     **/

    class project extends ModuleObject {

        /**
         * @brief 설치시 추가 작업이 필요할시 구현
         **/
        function moduleInstall() {
            return new Object();
        }

        /**
         * @brief 설치가 이상이 없는지 체크하는 method
         **/
        function checkUpdate() {
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            if(!$oModuleModel->getTrigger('moduleHandler.init', 'project', 'controller', 'triggerProjectAccount', 'after')) return true;
            if(!$oModuleModel->getTrigger('moduleHandler.proc', 'project', 'controller', 'triggerApplyLayout', 'after')) return true;
            if(!$oModuleModel->getTrigger('display', 'project', 'controller', 'triggerMemberMenu', 'before')) return true;
			// new_item triggers
			if(!$oModuleModel->getTrigger('document.insertDocument', 'project', 'controller', 'triggerInsertDocument', 'after')) return true;
			if(!$oModuleModel->getTrigger('document.deleteDocument', 'project', 'controller', 'triggerDeleteDocument', 'after')) return true;
			if(!$oModuleModel->getTrigger('comment.insertComment', 'project', 'controller', 'triggerInsertComment', 'after')) return true;
			if(!$oModuleModel->getTrigger('comment.deleteComment', 'project', 'controller', 'triggerDeleteComment', 'after')) return true;

			if(!$oModuleModel->getTrigger('issuetracker.insertRelease', 'project', 'controller', 'triggerInsertRelease', 'after')) return true;
			if(!$oModuleModel->getTrigger('member.addMemberToGroup', 'project', 'controller', 'triggerAddMemberToGroup', 'after')) return true;
	
			$output = executeQueryArray("project.checkPoints");
			if(!$output->data) return true;

			$output = executeQueryArray("project.checkActivityPoints");
			if(!$output->data) return true;

			if(!$oModuleModel->getTrigger('issuetracker.insertChangeset', 'project', 'controller', 'triggerInsertChangeset', 'after')) return true;
			if(!$oModuleModel->getTrigger('file.downloadFile', 'project', 'controller', 'triggerFileDownload', 'after')) return true;
			if(!$oModuleModel->getTrigger('issuetracker.insertHistory', 'project', 'controller', 'triggerInsertHistory', 'after')) return true;
            if(!$oModuleModel->getTrigger('document.moveDocumentModule', 'project', 'controller', 'triggerMoveDocumentModule', 'after')) return true;
			if(!$oModuleModel->getTrigger('issuetracker.deleteRelease', 'project', 'controller', 'triggerDeleteRelease', 'after')) return true;
            return false;
        }

        /**
         * @brief 업데이트 실행
         **/
        function moduleUpdate() {
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            if(!$oModuleModel->getTrigger('moduleHandler.init', 'project', 'controller', 'triggerProjectAccount', 'after')) 
                $oModuleController->insertTrigger('moduleHandler.init', 'project', 'controller', 'triggerProjectAccount', 'after');
            if(!$oModuleModel->getTrigger('moduleHandler.proc', 'project', 'controller', 'triggerApplyLayout', 'after')) 
                $oModuleController->insertTrigger('moduleHandler.proc', 'project', 'controller', 'triggerApplyLayout', 'after');
            if(!$oModuleModel->getTrigger('display', 'project', 'controller', 'triggerMemberMenu', 'before')) 
                $oModuleController->insertTrigger('display', 'project', 'controller', 'triggerMemberMenu', 'before');
			if(!$oModuleModel->getTrigger('document.insertDocument', 'project', 'controller', 'triggerInsertDocument', 'after')) 
				$oModuleController->insertTrigger('document.insertDocument', 'project', 'controller', 'triggerInsertDocument', 'after');
			if(!$oModuleModel->getTrigger('document.deleteDocument', 'project', 'controller', 'triggerDeleteDocument', 'after')) 
				$oModuleController->insertTrigger('document.deleteDocument', 'project', 'controller', 'triggerDeleteDocument', 'after');
			if(!$oModuleModel->getTrigger('comment.insertComment', 'project', 'controller', 'triggerInsertComment', 'after')) 
				$oModuleController->insertTrigger('comment.insertComment', 'project', 'controller', 'triggerInsertComment', 'after');
			if(!$oModuleModel->getTrigger('comment.deleteComment', 'project', 'controller', 'triggerDeleteComment', 'after')) 
				$oModuleController->insertTrigger('comment.deleteComment', 'project', 'controller', 'triggerDeleteComment', 'after');
			if(!$oModuleModel->getTrigger('member.addMemberToGroup', 'project', 'controller', 'triggerAddMemberToGroup', 'after')) 
				$oModuleController->insertTrigger('member.addMemberToGroup', 'project', 'controller', 'triggerAddMemberToGroup', 'after');

			$output = executeQueryArray("project.checkPoints");
			if(!$output->data) {
				$points = array("d" => 2, "c" => 1, "s"=> 3);
				foreach($points as $type => $point)
				{
					$args = null;
					$args->type = $type;
					$args->point = $point;
					executeQuery("project.insertContPoint", $args);
				}
			}

			$output = executeQueryArray("project.checkActivityPoints");
			if(!$output->data) {
				$points = array("d" => 2, "c" => 1, "s" => 3, "m" => 2, "D" => 10, "r" => 2);
				foreach($points as $type => $point)
				{
					$args = null;
					$args->type = $type;
					$args->point = $point;
					executeQuery("project.insertActivityPoint", $args);
				}
			}
			

			if(!$oModuleModel->getTrigger('issuetracker.insertRelease', 'project', 'controller', 'triggerInsertRelease', 'after')) 
				$oModuleController->insertTrigger('issuetracker.insertRelease', 'project', 'controller', 'triggerInsertRelease', 'after');

			if(!$oModuleModel->getTrigger('issuetracker.insertChangeset', 'project', 'controller', 'triggerInsertChangeset', 'after')) 
				$oModuleController->insertTrigger('issuetracker.insertChangeset', 'project', 'controller', 'triggerInsertChangeset', 'after');

			if(!$oModuleModel->getTrigger('file.downloadFile', 'project', 'controller', 'triggerFileDownload', 'after')) 
				$oModuleController->insertTrigger('file.downloadFile', 'project', 'controller', 'triggerFileDownload', 'after'); 

			if(!$oModuleModel->getTrigger('issuetracker.insertHistory', 'project', 'controller', 'triggerInsertHistory', 'after'))
				$oModuleController->insertTrigger('issuetracker.insertHistory', 'project', 'controller', 'triggerInsertHistory', 'after');

            if(!$oModuleModel->getTrigger('document.moveDocumentModule', 'project', 'controller', 'triggerMoveDocumentModule', 'after')) 
				$oModuleController->insertTrigger('document.moveDocumentModule', 'project', 'controller', 'triggerMoveDocumentModule', 'after'); 

			if(!$oModuleModel->getTrigger('issuetracker.deleteRelease', 'project', 'controller', 'triggerDeleteRelease', 'after')) 
				$oModuleController->insertTrigger('issuetracker.deleteRelease', 'project', 'controller', 'triggerDeleteRelease', 'after');

            return new Object(0, 'success_updated');
        }

        /**
         * @brief 캐시 파일 재생성
         **/
        function recompileCache() {
        }
    }
?>
