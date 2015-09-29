<?php

/**
 * @version     1.0.0
 * @package     com_citybranding
 * @copyright   Copyright (C) 2015. All rights reserved.
 * @license     GNU AFFERO GENERAL PUBLIC LICENSE Version 3; see LICENSE
 * @author      Ioannis Tsampoulatidis <tsampoulatidis@gmail.com> - https://github.com/itsam
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Methods supporting a list of Citybranding records.
 */
class CitybrandingModelVotes extends JModelList {

    /**
     * Constructor.
     *
     * @param    array    An optional associative array of configuration settings.
     * @see        JController
     * @since    1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'poiid', 'a.poiid',
                'created', 'a.created',
                'updated', 'a.updated',
                'ordering', 'a.ordering',
                'state', 'a.state',
                'created_by', 'a.created_by',

            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     */
    protected function populateState($ordering = null, $direction = null) {
        // Initialise variables.
        $app = JFactory::getApplication('administrator');

        // Load the filter state.
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '', 'string');
        $this->setState('filter.state', $published);

        
		//Filtering poiid
		$this->setState('filter.poiid', $app->getUserStateFromRequest($this->context.'.filter.poiid', 'filter_poiid', '', 'string'));


        // Load the parameters.
        $params = JComponentHelper::getParams('com_citybranding');
        $this->setState('params', $params);

        // List state information.
        parent::populateState('a.id', 'asc');
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param	string		$id	A prefix for the store id.
     * @return	string		A store id.
     * @since	1.6
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id.= ':' . $this->getState('filter.search');
        $id.= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return	JDatabaseQuery
     * @since	1.6
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__citybranding_votes` AS a');

        
		// Join over the users for the checked out user
		$query->select("uc.name AS editor");
		$query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");
		// Join over the foreign key 'poiid'
		$query->select('#__citybranding_pois_1382359.title AS pois_title_1382359');
		$query->join('LEFT', '#__citybranding_pois AS #__citybranding_pois_1382359 ON #__citybranding_pois_1382359.id = a.poiid');
		// Join over the user field 'created_by'
		$query->select('created_by.name AS created_by');
		$query->join('LEFT', '#__users AS created_by ON created_by.id = a.created_by');

        

		// Filter by published state
		$published = $this->getState('filter.state');
		if (is_numeric($published)) {
			$query->where('a.state = ' . (int) $published);
		} else if ($published === '') {
			$query->where('(a.state IN (0, 1))');
		}

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                
            }
        }

        

		//Filtering poiid
		$filter_poiid = $this->state->get("filter.poiid");
		if ($filter_poiid) {
			$query->where("a.poiid = '".$db->escape($filter_poiid)."'");
		}


        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering');
        $orderDirn = $this->state->get('list.direction');
        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    public function getItems() {
        $items = parent::getItems();
        
/*		foreach ($items as $oneItem) {

			if (isset($oneItem->poiid)) {
				$values = explode(',', $oneItem->poiid);

				$textValue = array();
				foreach ($values as $value){
					$db = JFactory::getDbo();
					$query = $db->getQuery(true);
					$query
							->select('title')
							->from('`#__citybranding_pois`')
							->where('id = ' . $db->quote($db->escape($value)));
					$db->setQuery($query);
					$results = $db->loadObject();
					if ($results) {
						$textValue[] = $results->title;
					}
				}

			$oneItem->poiid = !empty($textValue) ? implode(', ', $textValue) : $oneItem->poiid;

			}
		}*/
        return $items;
    }

    private function hasVoted($poiid, $userid) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from('`#__citybranding_votes` AS a');
        $query->where('a.poiid    = ' . $db->quote($db->escape($poiid)));
        $query->where('a.created_by = ' . $db->quote($db->escape($userid)));
        $db->setQuery($query);
        $results = $db->loadResult();
        return $results;
    }

    public function add($poiid, $userid, $modality = 0) {
        // check if already voted    
        if($this->hasVoted($poiid, $userid)){
            return array('code'=>0, 'msg'=>JText::_('COM_CITYBRANDING_VOTES_ALREADY_VOTED'));
        }
        $poisModel = JModelLegacy::getInstance( 'Pois', 'CitybrandingModel', array('ignore_request' => true) );
        // check if it's own poi
        if($poisModel->isOwnPoi($poiid, $userid)){
            return array('code'=>0, 'msg'=>JText::_('COM_CITYBRANDING_VOTES_OWN_POI'));    
        }

        // Create and populate an object.
        $vote = new stdClass();
        $vote->poiid = $poiid;
        $vote->created_by = $userid;
        $vote->state = 1;
        $vote->modality = $modality;
        $vote->created = date('Y-m-d H:i:s');
        $vote->updated = date('Y-m-d H:i:s');
         
        // Insert the object into the votes table.
        $db = JFactory::getDbo();
        $result = $db->insertObject('#__citybranding_votes', $vote); 
        if($result){
            //update poi votes as well
            $result = $poisModel->updateVotes($poiid, $userid);
            if($result){
                //also return current number of votes 
                $votes = $poisModel->getVotes($poiid);
                return array('code'=>1, 'msg'=>JText::_('COM_CITYBRANDING_VOTES_ADDED'), 'votes'=>$votes);
            }
            else {
                return array('code'=>-1, 'msg'=>'failed to update poi');
            }
        } else {
            return array('code'=>-1, 'msg'=>'failed to insert into votes table');
        }


    }
}