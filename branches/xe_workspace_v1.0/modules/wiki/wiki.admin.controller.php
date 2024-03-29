<?php
	/**
	 * @class  wikiAdminController
	 * @author NHN (developers@xpressengine.com)
	 * @brief  wiki 모듈의 admin controller class
	 **/

	class wikiAdminController extends wiki {

		/**
		 * @brief 초기화
		 **/
		function init() {
		}

		/**
		 * @brief 위키 모듈 추가
		 */
		function procWikiAdminInsertWiki($args = null) {
			// module 모듈의 model/controller 객체 생성
			$oModuleController = &getController('module');
			$oModuleModel = &getModel('module');

			if(!$args) $args = Context::getRequestVars();
			$args->module = 'wiki';
			$args->mid = $args->wiki_name;
			if($args->use_comment!='N') $args->use_comment = 'Y';

			unset($args->wiki_name);
			if($args->module_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
				if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
			}

			if(!$args->module_srl) {
				$output = $oModuleController->insertModule($args);
				$msg_code = 'success_registed';
			} else {
				$output = $oModuleController->updateModule($args);
				$msg_code = 'success_updated';
			}

			if(!$output->toBool()) return $output;

			$this->add('page',Context::get('page'));
			$this->add('module_srl',$output->get('module_srl'));
			$this->setMessage($msg_code);
		}

		/**
		 * @brief 위키 모듈 삭제
		 */
		function procWikiAdminDeleteWiki() {
			$module_srl = Context::get('module_srl');

			$oModuleController = &getController('module');
			$output = $oModuleController->deleteModule($module_srl);
			if(!$output->toBool()) return $output;

			$this->add('module','wiki');
			$this->add('page',Context::get('page'));
			$this->setMessage('success_deleted');
		}

		/**
		 * @brief 제목으로 접근할 수 없는 글을 정리
		 */
		function procWikiAdminArrangeList() {
			$oModuleModel = &getModel('module');
			$oDocumentController = &getController('document');

			// 대상 위키 검증
			$module_srl = Context::get('module_srl');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info->module_srl || $module_info->module != 'wiki') return new Object(-1,'msg_invalid_request');

			// 대상 위키의 entry값이 없는 글을 추출
			$args->module_srl = $module_srl;
			$output = executeQueryArray('wiki.getDocumentWithoutAlias', $args);
			if(!$output->toBool() || !$output->data) return new Object();

			foreach($output->data as $key => $val) {
				if($val->alias_srl) continue;
				$result = $oDocumentController->insertAlias($module_srl, $val->document_srl, $val->alias_title);
				if(!$result->toBool()) $oDocumentController->insertAlias($module_srl, $val->document_srl, $val->alias_title.'_'.$val->document_srl);
			}
		}

	}
?>
