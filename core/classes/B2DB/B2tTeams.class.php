<?php

	/**
	 * Teams table
	 *
	 * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
	 * @version 2.0
	 * @license http://www.opensource.org/licenses/mozilla1.1.php Mozilla Public License 1.1 (MPL 1.1)
	 * @package thebuggenie
	 * @subpackage tables
	 */

	/**
	 * Teams table
	 *
	 * @package thebuggenie
	 * @subpackage tables
	 */
	class B2tTeams extends B2DBTable 
	{

		const B2DBNAME = 'teams';
		const ID = 'teams.id';
		const SCOPE = 'teams.scope';
		const TEAMNAME = 'teams.teamname';
		
		public function __construct()
		{
			parent::__construct(self::B2DBNAME, self::ID);
			
			parent::_addVarchar(self::TEAMNAME, 50);
			parent::_addForeignKeyColumn(self::SCOPE, B2DB::getTable('B2tScopes'), B2tScopes::ID);
		}

		public function loadFixtures($scope_id)
		{
			$i18n = BUGScontext::getI18n();

			$crit = $this->getCriteria();
			$crit->addInsert(B2tTeams::TEAMNAME, $i18n->__('Staff members'));
			$crit->addInsert(B2tTeams::SCOPE, $scope_id);
			$this->doInsert($crit);

			$crit = $this->getCriteria();
			$crit->addInsert(B2tTeams::TEAMNAME, $i18n->__('Developers'));
			$crit->addInsert(B2tTeams::SCOPE, $scope_id);
			$this->doInsert($crit);

			$crit = $this->getCriteria();
			$crit->addInsert(B2tTeams::TEAMNAME, $i18n->__('Team leaders'));
			$crit->addInsert(B2tTeams::SCOPE, $scope_id);
			$this->doInsert($crit);
		}
		
	}
