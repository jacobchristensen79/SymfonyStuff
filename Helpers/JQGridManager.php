<?php

namespace MyBundle\ModelBundle\Helper;

/**
 * Intented to simplify JQgrid layout and search filters
 * @author Jacob
 *
 */
class JQGridManager
{
	const MAX_LIMIT = 100;
	
	// Columns
	private $_collumns;

    // Translated columns
    private $_transCol = array();
	
	// EntityManager
	private $_entityManager;
	private $_model;
	
	// Return data
	private $_result;
	
	// Search And Sort
	private $_search = array();
	
	// Special Date Time fields
	private $_special_return = array(
			'birth_date' => array('format'=>'Y-m-d'),
			'created_at' => array('format'=>'Y-m-d'),
	);

    // Translator
    private $_translator;

    public function __construct($em, $translator)
    {
        $this->_entityManager = $em;
        $this->_translator = $translator;
    }
	
	
	/**
	 * Set used return collumns
	 */
	public function setCollumns(array $cols)
	{
		$this->_collumns = $cols;
	}

    /**
     * Set translated columns
     */
    public function setTransCol(array $transCol)
    {
        $this->_transCol = $transCol;
    }

    /**
	 * Set Entity Manager
	 */
	public function setEntityManager($em)
	{
		$this->_entityManager = $em;
	}
	
	/**
	 * Set Model
	 */
	public function setModel($model)
	{
		$this->_model = $model;
	}

    /**
     * Set parameters
     * @param $request
     * @param bool/array $searcher Search from datatable
     * @param bool/array $filter Filter ever in datatable
     */
    public function setParameters($request, $searcher = false, $filters = false)
	{
		$this->_search['iDisplayStart'] = $request->get('iDisplayStart',1);
		$this->_search['sEcho'] = $request->get('sEcho');
		// Limit
		$this->_search['limit'] = $request->get('iDisplayLength',10);
		// order by
		$this->_search['orderby'] = $request->get('sidx','id');		
		$this->_search['orderdir'] = $request->get('sord','desc');
		// Search Filters
		// filters	{"groupOp":"AND","rules":[{"field":"name","op":"eq","data":"User"}]}
		$this->_search['filters'] = $request->get('sSearch',false);

        if($this->_search['filters'] && $searcher)
        {
            $this->_search['filters'] = array(
                'groupOp' => 'AND',
                'rules'     => array(
                    array(
                        'field' => $searcher,
                        'op'    => 'bw',
                        'data'  => $this->_search['filters']
                    )
                )
            );

        }

        if($filters)
        {
            $this->_search['filters']['groupOp'] = 'AND';

            foreach($filters as $filter)
            {
                $this->_search['filters']['rules'][] = array(
                    'field' => $filter['field'],
                    'op'    => $filter['op'],
                    'data'  => $filter['data']
                );
            }
        }

        $this->_search['filters'] = json_encode( $this->_search['filters']);
	}
	
	/**
	 * Result As Array
	 */
	public function getResult()
	{
		$qb = $this->_entityManager->createQueryBuilder();
		// Return data
		$data = array(
				"sEcho"=>$this->_search['sEcho'],
				"iTotalRecords"=>0,
				"iTotalDisplayRecords"=>0,
				"aaData"=>array()
		);
		
		
		// TOTAL QUERY
		$totalsql = $qb->select('COUNT(o.id)')->from($this->_model, 'o');
		// Build where, like....
		$totalsql = $this->buildQuery($totalsql);

		// TOTAL
		$total = $totalsql->getQuery()->getSingleScalarResult();
        $total = ($total) ? $total : 1;
		$data['iTotalRecords'] = ceil($total/$this->_search['limit']);

		// Early return if no results
		if ($data['iTotalRecords']==0) return $data;

		// Data Query
		$query = $qb->select('o');
		// Build where, like....
		//$query = $this->buildQuery($query);		
		
		// set limits to all
		$query = $this->buildLimitAndOrder($query);
		$results = $query->getQuery()->getResult();
		
		// Build Get Names
		$cols = array();
		foreach ($this->_collumns as $col){
			$cols[$col] = 'get'.$this->doCamlize($col);
		}
		
		// Build Out Data Array
		$rows = array();
		foreach ($results as $key=>$result){
			foreach( $cols as $name=>$col){
				if ( array_key_exists($name,$this->_special_return) ){
					$obj = $result->$col();
					$convert = array_keys($this->_special_return[$name]);
					$convert = reset($convert);				
					$format = $this->_special_return[$name][$convert];
					$rows[$key][$name] = $obj->$convert($format);
				} else {
                    if( !empty($this->_transCol) && array_key_exists($name, $this->_transCol) ){
                        $prefix = $this->_transCol[$name];
                        $content = $this->_translator->trans($prefix.$result->$col());
                    }else{
                        $content = $result->$col();
                    }
                    $rows[$key][$name] = (string) $content;
				}
			}
		}
		// Completing return data
		$data['iTotalDisplayRecords'] = count($rows);
		$data['aaData']	 = $rows;

		
		return $data;
		
	}
	
	/**
	 * Private function to make Camilizes
	 */
	private function doCamlize($name)
	{
		return strtr(ucwords(strtr($name, array('_' => ' ', '.' => '_ '))), array(' ' => ''));
	}
	
	/**
	 * Build Query
	 * @return Query
	 * //if(isset($search['status'])) $qb->andWhere('u.status ='.$search['status']);
	 */
	private function buildQuery($query)
	{
		if ( !$this->_search['filters'] ) return $query;
		
		$filters = json_decode($this->_search['filters']);
		if ( !$filters ) return $query;
		
		$operator = (in_array($filters->groupOp, array('OR','AND'))) ?
			$filters->groupOp : 'AND';
		
		$first = true;
		// Query Operations
		foreach ($filters->rules as $rule){
			//if( !in_array($rule->field, $this->_collumns)) continue;
			$value = strip_tags(trim($rule->data));

            // En UserLog tenemos que convertir el valor de Ip recibido en caso de que se use
            if($rule->field == 'ip'){
                $value = ip2long($value);
            }
            
			$str = '';
			switch ($rule->op){
				case"eq": //equal
					$str = "o.$rule->field='{$value}'";
					break;
				case"ne": //not equal
					$str = "o.$rule->field != '{$value}'";
					break;
				case"bw": //begins with
					$str = "o.$rule->field LIKE '{$value}%'";
					break;
				case"bn": //does not begin with
					$str = "o.$rule->field NOT LIKE '{$value}%'";
					break;
				case"ew": //ends with
					$str = "o.$rule->field LIKE '%{$value}'";
					break;
				case"en": //does not end with
					$str = "o.$rule->field NOT LIKE '%{$value}'";
					break;
				case"cn": //contains
					$str = "o.$rule->field LIKE '%{$value}%'";
					break;
				case"nc": //does not contain
					$str = "o.$rule->field NOT LIKE '%{$value}%'";
					break;
				case"in": //is in  
                    $str = "o.$rule->field IN ({$value})";
					break;
				case"ni": //is not in 
                    $str = "o.$rule->field NOT IN ({$value})";
					break;
                case"entity_in": //is in
                    $query->join('o.'.$rule->field, 'o2');
                    $str = "o2.id IN ({$value})";
                    break;
			}

			if ( $str=='' ) continue;
			if ( $first ) 				 $query->where($str);
			else if( 'AND' == $operator) $query->andWhere($str);
			else 						 $query->orWhere($str);
			
			$first = false;
		}
		return $query;
	}
	
	/**
	 * Build OrderBy
	 * @return Query
	 */
	private function buildLimitAndOrder($query)
	{
		$limit = (int) $this->_search['limit'];
		if ($limit > $this::MAX_LIMIT) $limit = $this::MAX_LIMIT;
		
		$orderby = (in_array($this->_search['orderby'], $this->_collumns)) ?
			$this->_search['orderby'] : 'id';
		$orderdir = (in_array($this->_search['orderdir'], array('asc','desc'))) ?
			$this->_search['orderdir'] : 'asc';
				
		return $query->setMaxResults($limit)
			->setFirstResult($this->_search['iDisplayStart'])
			->orderBy('o.'.$orderby, $orderdir);
	}
}