<?php

	/**
	 * actions for the search module
	 */
	class searchActions extends BUGSaction
	{

		protected $foundissues = array();
		protected $filters = array();

		/**
		 * Pre-execute function for search functions
		 *
		 * @param BUGSrequest $request
		 */
		public function preExecute(BUGSrequest $request, $action)
		{
			$this->forward403unless(BUGScontext::getUser()->hasPageAccess('search'));
			if ($request->hasParameter('project_key'))
			{
				if (($project = BUGSproject::getByKey($request->getParameter('project_key'))) instanceof BUGSproject)
				{
					BUGScontext::getResponse()->setPage('project_issues');
					BUGScontext::setCurrentProject($project);
					$this->getResponse()->setProjectMenuStripHidden(false);
				}
			}
			else
			{
				BUGScontext::getResponse()->setProjectMenuStripHidden();
			}
		}

		/**
		 * Performs quicksearch
		 * 
		 * @param BUGSrequest $request The request object
		 */		
		public function runQuickSearch(BUGSrequest $request)
		{
			$this->searchterm = $request->getParameter('searchfor');
			$results = array();

			if ($this->searchterm != '')
			{
				$issue = BUGSissue::getIssueFromLink($this->searchterm);
				if ($issue instanceof BUGSissue)
				{
					if (!BUGScontext::isProjectContext() || (BUGScontext::isProjectContext() && $issue->getProjectID() == BUGScontext::getCurrentProject()->getID()))
					{
						$results[] = $issue;
					}
				}
			}

			$this->results = $results;
		}

		protected function _getSearchDetailsFromRequest(BUGSrequest $request)
		{
			$this->searchterm = $request->getParameter('searchfor', false);
			$this->ipp = $request->getParameter('issues_per_page', 30);
			$this->offset = $request->getParameter('offset', 0);
			$this->filters = $request->getParameter('filters', array());
			if (BUGScontext::isProjectContext())
			{
				$this->filters['project_id'][0] = array('operator' => '=', 'value' => BUGScontext::getCurrentProject()->getID());
			}
			$this->groupby = $request->getParameter('groupby');
			$this->grouporder = $request->getParameter('grouporder', 'asc');
			$this->predefined_search = $request->getParameter('predefined_search', false);
			$this->templatename = ($request->hasParameter('template') && in_array($request->getParameter('template'), array_keys($this->getTemplates(false)))) ? $request->getParameter('template') : 'results_normal';
			$this->searchtitle = __('Search results');
			$this->issavedsearch = false;

			if ($request->hasParameter('saved_search'))
			{
				$savedsearch = B2DB::getTable('B2tSavedSearches')->doSelectById($request->getParameter('saved_search'));
				if ($savedsearch instanceof B2DBRow && BUGScontext::getUser()->canAccessSavedSearch($savedsearch))
				{
					$this->issavedsearch = true;
					$this->savedsearch = $savedsearch;
					$this->templatename = $savedsearch->get(B2tSavedSearches::TEMPLATE_NAME);
					$this->groupby = $savedsearch->get(B2tSavedSearches::GROUPBY);
					$this->grouporder = $savedsearch->get(B2tSavedSearches::GROUPORDER);
					$this->ipp = $savedsearch->get(B2tSavedSearches::ISSUES_PER_PAGE);
					$this->searchtitle = $savedsearch->get(B2tSavedSearches::NAME);
					$this->filters = B2DB::getTable('B2tSavedSearchFilters')->getFiltersBySavedSearchID($savedsearch->get(B2tSavedSearches::ID));
				}
			}
		}

		protected function doSearch(BUGSrequest $request)
		{
			$i18n = BUGScontext::getI18n();
			if ($this->searchterm)
			{
				preg_replace_callback('#(?<!\!)((bug|issue|ticket|story)\s\#?(([A-Z0-9]+\-)?\d+))#i', array($this, 'extractIssues'), $this->searchterm);
			}

			if (count($this->foundissues) == 0)
			{
				if ($request->hasParameter('predefined_search'))
				{
					switch ((int) $request->getParameter('predefined_search'))
					{
						case BUGScontext::PREDEFINED_SEARCH_PROJECT_OPEN_ISSUES:
							$this->filters['state'] = array('operator' => '=', 'value' => BUGSissue::STATE_OPEN);
							break;
						case BUGScontext::PREDEFINED_SEARCH_PROJECT_CLOSED_ISSUES:
							$this->filters['state'] = array('operator' => '=', 'value' => BUGSissue::STATE_CLOSED);
							break;
						case BUGScontext::PREDEFINED_SEARCH_PROJECT_MILESTONE_TODO:
							$this->groupby = 'milestone';
							break;
						case BUGScontext::PREDEFINED_SEARCH_MY_REPORTED_ISSUES:
							$this->filters['posted_by'] = array('operator' => '=', 'value' => BUGScontext::getUser()->getID());
							break;
						case BUGScontext::PREDEFINED_SEARCH_MY_ASSIGNED_OPEN_ISSUES:
							$this->filters['state'] = array('operator' => '=', 'value' => BUGSissue::STATE_OPEN);
							$this->filters['assigned_type'] = array('operator' => '=', 'value' => BUGSidentifiableclass::TYPE_USER);
							$this->filters['assigned_to'] = array('operator' => '=', 'value' => BUGScontext::getUser()->getID());
							break;
						case BUGScontext::PREDEFINED_SEARCH_TEAM_ASSIGNED_OPEN_ISSUES:
							$this->filters['state'] = array('operator' => '=', 'value' => BUGSissue::STATE_OPEN);
							$this->filters['assigned_type'] = array('operator' => '=', 'value' => BUGSidentifiableclass::TYPE_TEAM);
							foreach (BUGScontext::getUser()->getTeams() as $team_id => $team)
							{
								$this->filters['assigned_to'][] = array('operator' => '=', 'value' => $team_id);
							}
							break;
					}
				}
				list ($this->foundissues, $this->resultcount) = BUGSissue::findIssues($this->filters, $this->ipp, $this->offset, $this->groupby, $this->grouporder);
			}
			elseif (count($this->foundissues) == 1 && $request->getParameter('quicksearch'))
			{
				$issue = array_shift($this->foundissues);
				$this->forward(BUGScontext::getRouting()->generate('viewissue', array('project_key' => $issue->getProject()->getKey(), 'issue_no' => $issue->getFormattedIssueNo())));
			}
			elseif ($request->hasParameter('sortby'))
			{

			}
			else
			{
				$this->resultcount = count($this->foundissues);
			}

			if ($request->hasParameter('predefined_search'))
			{
				switch ((int) $request->getParameter('predefined_search'))
				{
					case BUGScontext::PREDEFINED_SEARCH_PROJECT_OPEN_ISSUES:
						$this->searchtitle = (BUGScontext::isProjectContext()) ? $i18n->__('Open issues for %project_name%', array('%project_name%' => BUGScontext::getCurrentProject()->getName())) : $i18n->__('All open issues');
						break;
					case BUGScontext::PREDEFINED_SEARCH_PROJECT_CLOSED_ISSUES:
						$this->searchtitle = (BUGScontext::isProjectContext()) ? $i18n->__('Closed issues for %project_name%', array('%project_name%' => BUGScontext::getCurrentProject()->getName())) : $i18n->__('All closed issues');
						break;
					case BUGScontext::PREDEFINED_SEARCH_PROJECT_MILESTONE_TODO:
						$this->searchtitle = $i18n->__('Milestone todo-list for %project_name%', array('%project_name%' => BUGScontext::getCurrentProject()->getName()));
						$this->templatename = 'results_todo';
						break;
					case BUGScontext::PREDEFINED_SEARCH_MY_ASSIGNED_OPEN_ISSUES:
						$this->searchtitle = $i18n->__('Open issues assigned to me');
						break;
					case BUGScontext::PREDEFINED_SEARCH_TEAM_ASSIGNED_OPEN_ISSUES:
						$this->searchtitle = $i18n->__('Open issues assigned to my teams');
						break;
					case BUGScontext::PREDEFINED_SEARCH_MY_REPORTED_ISSUES:
						$this->searchtitle = $i18n->__('Issues reported by me');
						break;
				}
			}

		}

		protected function getTemplates($display_only = true)
		{
			$templates = array();
			$templates['results_normal'] = BUGScontext::getI18n()->__('Standard search results');
			$templates['results_todo'] = BUGScontext::getI18n()->__('Todo-list with progress indicator');
			if (!$display_only)
			{
				$templates['results_rss'] = BUGScontext::getI18n()->__('RSS feed');
			}
			return $templates;
		}

		/**
		 * Performs the "find issues" action
		 *
		 * @param BUGSrequest $request
		 */
		public function runFindIssues(BUGSrequest $request)
		{
			$this->show_results = ($request->hasParameter('filters') || $request->getParameter('search', false)) ? true : false;
			$this->_getSearchDetailsFromRequest($request);

			if ($request->isMethod(BUGSrequest::POST))
			{
				if ($request->getParameter('saved_search_name') != '')
				{
					$project_id = (BUGScontext::isProjectContext()) ? BUGScontext::getCurrentProject()->getID() : 0;
					B2DB::getTable('B2tSavedSearches')->saveSearch($request->getParameter('saved_search_name'), $request->getParameter('saved_search_description'), $request->getParameter('saved_search_public'), $this->filters, $this->groupby, $this->grouporder, $this->ipp, $this->templatename, $project_id, $request->getParameter('saved_search_id'));
					if ($request->getParameter('saved_search_id'))
					{
						BUGScontext::setMessage('search_message', BUGScontext::getI18n()->__('The saved search was updated'));
					}
					else
					{
						BUGScontext::setMessage('search_message', BUGScontext::getI18n()->__('The saved search has been created'));
					}
					$params = array();
				}
				else
				{
					BUGScontext::setMessage('search_error', BUGScontext::getI18n()->__('You have to specify a name for the saved search'));
					$params = array('filters' => $this->filters, 'groupby' => $this->groupby, 'grouporder' => $this->grouporder, 'templatename' => $this->templatename, 'saved_search' => $request->getParameter('saved_search_id'), 'issues_per_page' => $this->ipp);
				}
				if (BUGScontext::isProjectContext())
				{
					$route = 'project_issues';
					$params['project_key'] = BUGScontext::getCurrentProject()->getKey();
				}
				else
				{
					$route = 'search';
				}
				//var_dump($params);die();
				$this->forward(BUGScontext::getRouting()->generate($route, $params));
			}
			elseif ($this->show_results)
			{
				$this->doSearch($request);
				$this->issues = $this->foundissues;
			}
			$this->search_error = BUGScontext::getMessageAndClear('search_error');
			$this->search_message = BUGScontext::getMessageAndClear('search_message');
			$this->appliedfilters = $this->filters;
			$this->templates = $this->getTemplates();
			
			$this->savedsearches = B2DB::getTable('B2tSavedSearches')->getAllSavedSearchesByUserIDAndPossiblyProjectID(BUGScontext::getUser()->getID(), (BUGScontext::isProjectContext()) ? BUGScontext::getCurrentProject()->getID() : 0);
			
			if ($request->getParameter('format') == 'rss')
			{
				return $this->renderComponent('search/results_rss', array('issues' => $this->issues, 'searchtitle' => $this->searchtitle));
			}
		}

		public function runFindIssuesPaginated(BUGSrequest $request)
		{
			$this->_getSearchDetailsFromRequest($request);

			if ($request->hasParameter('searchfor'))
			{
				$this->doSearch($request);
				$this->issues = $this->foundissues;
			}
			$this->appliedfilters = $this->filters;
			$this->templates = $this->getTemplates();
		}

		public function runAddFilter(BUGSrequest $request)
		{
			if (in_array($request->getParameter('filter_name'), B2tIssues::getValidSearchFilters()) || BUGScustomdatatype::doesKeyExist($request->getParameter('filter_name')))
			{
				return $this->renderJSON(array('failed' => false, 'content' => $this->getComponentHTML('search/filter', array('filter' => $request->getParameter('filter_name'), 'key' => $request->getParameter('key', 0)))));
			}
			else
			{
				return $this->renderJSON(array('failed' => true, 'error' => BUGScontext::getI18n()->__('This is not a valid search field')));
			}
		}

		protected function extractIssues($matches)
		{
			$issue = BUGSissue::getIssueFromLink($matches[0]);
			if ($issue instanceof BUGSissue)
			{
				if (!BUGScontext::isProjectContext() || (BUGScontext::isProjectContext() && $issue->getProjectID() == BUGScontext::getCurrentProject()->getID()))
				{
					$this->foundissues[$issue->getID()] = $issue;
				}
			}
		}

		static function resultGrouping(BUGSissue $issue, $groupby, $cc, $prevgroup_id)
		{
			$i18n = BUGScontext::getI18n();
			$showtablestart = false;
			$showheader = false;
			$groupby_id = 0;
			$groupby_description = '';
			if ($cc == 1) $showtablestart = true;
			if ($groupby != '')
			{
				switch ($groupby)
				{
					case 'category':
						if ($issue->getCategory() instanceof BUGScategory)
						{
							$groupby_id = $issue->getCategory()->getID();
							$groupby_description = $issue->getCategory()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Unknown');
						}
						break;
					case 'status':
						if ($issue->getStatus() instanceof BUGSstatus)
						{
							$groupby_id = $issue->getStatus()->getID();
							$groupby_description = $issue->getStatus()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Unknown');
						}
						break;
					case 'severity':
						if ($issue->getSeverity() instanceof BUGSseverity)
						{
							$groupby_id = $issue->getSeverity()->getID();
							$groupby_description = $issue->getSeverity()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Unknown');
						}
						break;
					case 'resolution':
						if ($issue->getResolution() instanceof BUGSresolution)
						{
							$groupby_id = $issue->getResolution()->getID();
							$groupby_description = $issue->getResolution()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Unknown');
						}
						break;
					case 'priority':
						if ($issue->getPriority() instanceof BUGSpriority)
						{
							$groupby_id = $issue->getPriority()->getID();
							$groupby_description = $issue->getPriority()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Unknown');
						}
						break;
					case 'issuetype':
						if ($issue->getIssueType() instanceof BUGSissuetype)
						{
							$groupby_id = $issue->getIssueType()->getID();
							$groupby_description = $issue->getIssueType()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Unknown');
						}
						break;
					case 'milestone':
						if ($issue->getMilestone() instanceof BUGSmilestone)
						{
							$groupby_id = $issue->getMilestone()->getID();
							$groupby_description = $issue->getMilestone()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Not targetted');
						}
						break;
					case 'assignee':
						if ($issue->getAssignee() instanceof BUGSidentifiableclass)
						{
							$groupby_id = $issue->getAssigneeID();
							$groupby_description = $issue->getAssignee()->getName();
						}
						else
						{
							$groupby_id = 0;
							$groupby_description = $i18n->__('Not assigned');
						}
						break;
					case 'state':
						if ($issue->isClosed())
						{
							$groupby_id = BUGSissue::STATE_CLOSED;
							$groupby_description = $i18n->__('Closed');
						}
						else
						{
							$groupby_id = BUGSissue::STATE_OPEN;
							$groupby_description = $i18n->__('Open');
						}
						break;
				}
				if ($groupby_id !== $prevgroup_id)
				{
					$showtablestart = true;
					$showheader = true;
				}
				$prevgroup_id = $groupby_id;
			}
			return array($showtablestart, $showheader, $prevgroup_id, $groupby_description);
		}

	}