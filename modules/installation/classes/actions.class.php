<?php

	class installationActions extends BUGSaction
	{
		
		/**
		 * Runs the installation action
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallIntro($request)
		{
			$this->getResponse()->setDecoration(BUGSresponse::DECORATE_NONE);
			
			if (($step = $request->getParameter('step')) && $step >= 1 && $step <= 6)
			{
				if ($step >= 5)
				{
					BUGScontext::setScope(1);
				}
				return $this->redirect('installStep'.$step);
			}
		}
		
		/**
		 * Runs the action for the first step of the installation
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallStep1($request)
		{
			$this->all_well = true;
			$this->b2db_folder_perm_ok = true;
			$this->base_folder_perm_ok = true;
			$this->b2db_param_file_ok = true;
			$this->pdo_ok = true;
			if (!is_writable(BUGScontext::getIncludePath() . 'core/B2DB/'))
			{
				$this->b2db_folder_perm_ok = false;
				$this->all_well = false;
			}
			if (file_exists(BUGScontext::getIncludePath() . 'core/B2DB/sql_parameters.inc.php') && !is_writable(BUGScontext::getIncludePath() . 'core/B2DB/sql_parameters.inc.php'))
			{
				$this->b2db_param_file_ok = false;
				$this->all_well = false;
			}
			if (!is_writable(BUGScontext::getIncludePath()))
			{
				$this->base_folder_perm_ok = false;
				$this->all_well = false;
			}
			if (!class_exists('PDO'))
			{
				$this->pdo_ok = false;
				$this->all_well = false;
			}
		}
		
		/**
		 * Runs the action for the second step of the installation
		 * where you enter database information
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallStep2($request)
		{
			$this->preloaded = false;
			$this->selected_connection_detail = 'dsn';
			
			if (!$this->error)
			{
				try
				{
					BaseB2DB::initialize();
				}
				catch (Exception $e)
				{
				}
				if (class_exists('B2DB'))
				{
					$this->preloaded = true;
					$this->username = B2DB::getUname();
					$this->password = B2DB::getPasswd();
					$this->dsn = B2DB::getDSN();
				}
			}
		}
		
		/**
		 * Runs the action for the third step of the installation
		 * where it tests the connection, sets up the database and the initial scope
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallStep3($request)
		{
			$this->selected_connection_detail = $request->getParameter('connection_type');
			try
			{
				if ($this->username = $request->getParameter('db_username'))
				{
					BaseB2DB::setUname($this->username);
					BaseB2DB::setTablePrefix($request->getParameter('db_prefix'));
					if ($this->password = $request->getParameter('db_password'))
					{
						BaseB2DB::setPasswd($this->password);
					}

					if ($this->selected_connection_detail == 'dsn')
					{
						if (($this->dsn = $request->getParameter('db_dsn')) != '')
						{
							BaseB2DB::setDSN($this->dsn);
						}
						else
						{
							throw new Exception('You must provide a valid DSN');
						}
					}
					else
					{
						if ($this->db_type = $request->getParameter('db_type'))
						{
							BaseB2DB::setDBtype($this->db_type);
							if ($this->db_hostname = $request->getParameter('db_hostname'))
							{
								BaseB2DB::setHost($this->db_hostname);
							}
							else
							{
								throw new Exception('You must provide a database hostname');
							}

							if ($this->db_port = $request->getParameter('db_port'))
							{
								BaseB2DB::setPort($this->db_port);
							}

							if ($this->db_databasename = $request->getParameter('db_name'))
							{
								BaseB2DB::setDBname($this->db_databasename);
							}
							else
							{
								throw new Exception('You must provide a database to use');
							}
						}
						else
						{
							throw new Exception('You must provide a database type');
						}
					}
					
					BaseB2DB::initialize(true);
					B2DB::doConnect();
					
					if (B2DB::getDBname() == '')
					{
						throw new Exception('You must provide a database to use');
					}
					B2DB::saveConnectionParameters();
					
				}
				else
				{
					throw new Exception('You must provide a database username');
				}
				
				// Add table classes to classpath 
				$tables_path = BUGS2_INCLUDE_PATH . 'core/classes/B2DB/';
				BUGScontext::addClasspath($tables_path);
				$tables_path_handle = opendir($tables_path);
				$tables_created = array();
				while ($table_class_file = readdir($tables_path_handle))
				{
					if (($tablename = substr($table_class_file, 0, strpos($table_class_file, '.'))) != '') 
					{
						B2DB::getTable($tablename)->create();
						$tables_created[] = $tablename;
					}
				}
				sort($tables_created);
				$this->tables_created = $tables_created;
				
				//BUGSscope::setupInitialScope();
				
			}
			catch (Exception $e)
			{
				//throw $e;
				$this->error = $e->getMessage();
			}
		}
		
		/**
		 * Runs the action for the fourth step of the installation
		 * where it loads fixtures and saves settings for url
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallStep4($request)
		{
			try
			{
				BUGSlogging::log('Initializing language support');
				BUGScontext::reinitializeI18n($request->getParameter('language'));

				BUGSlogging::log('Loading fixtures for default scope');
				$scope = BUGSscope::createNew('The default scope', '');

				BUGSlogging::log('Setting up default users and groups');
				BUGSsettings::saveSetting('language', $request->getParameter('language'), 'core', 1);
				$scope->setHostname($request->getParameter('url_host'));
				$scope->save();
				BUGSsettings::saveSetting('url_subdir', $request->getParameter('url_subdir'), 'core', 1);
			}
			catch (Exception $e)
			{
				$this->error = $e->getMessage();
                                throw $e;
			}
		}
		
		/**
		 * Runs the action for the fifth step of the installation
		 * where it enables modules on demand
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallStep5($request)
		{
			$this->sample_data = false;
			try
			{
				if ($request->hasParameter('modules'))
				{
					foreach ($request->getParameter('modules', array()) as $module => $install)
					{
						if ((bool) $install && file_exists(BUGScontext::getIncludePath() . "modules/{$module}/module"))
						{
							BUGScontext::addClasspath(BUGScontext::getIncludePath() . "modules/{$module}/classes/");
							if (file_exists(BUGScontext::getIncludePath() . "modules/{$module}/classes/B2DB/"))
							{
								BUGScontext::addClasspath(BUGScontext::getIncludePath() . "modules/{$module}/classes/B2DB/");
							}
							$classname = file_get_contents(BUGScontext::getIncludePath() . "modules/{$module}/class");
							call_user_func(array($classname, 'install'), 1);
						}
					}
				}
				elseif ($request->hasParameter('sample_data'))
				{
					$this->sample_data = true;
				}
			}
			catch (Exception $e)
			{
				throw $e;
				$this->error = $e->getMessage();
			}
		}
		
		/**
		 * Runs the action for the sixth step of the installation
		 * where it finalizes the installation
		 * 
		 * @param BUGSrequest $request The request object
		 * 
		 * @return null
		 */
		public function runInstallStep6($request)
		{
			if (file_put_contents(BUGScontext::getIncludePath() . 'installed', '2.1, installed ' . date('d.m.Y H:i')) === false)
			{
				$this->error = "Couldn't write to the main directory";
			}
		}
		
	}
