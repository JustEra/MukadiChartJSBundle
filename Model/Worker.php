<?php
/**
 * Created by PhpStorm.
 * User: Olivier
 * Date: 14/05/2017
 * Time: 15:18
 */

namespace Mukadi\ChartJSBundle\Model;


use Mukadi\ChartJSBundle\Model\Cruncher\ArrayData;
use Mukadi\ChartJSBundle\Model\Cruncher\ConditionalValueMapper;
use Mukadi\ChartJSBundle\Model\Cruncher\ValueMapper;
use Mukadi\ChartJSBundle\Model\Manipulator\Condition;
use Mukadi\ChartJSBundle\Model\Manipulator\GroupBy;
use Mukadi\ChartJSBundle\Model\Manipulator\ModelJoiner;
use Mukadi\ChartJSBundle\Model\Manipulator\ModelSetter;
use Mukadi\ChartJSBundle\Model\Manipulator\ValueSelector;

class Worker implements WorkerInterface{
    /**
     * @var array
     */
    protected $query;
    /**
     * @var array
     */
    protected $params;
    /**
     * @var DataCruncherInterface
     */
    protected $labels;
    /**
     * @var array
     */
    protected $valuesConfigs;
    /**
     * @var array
     */
    protected $values;
    /**
     * @var integer
     */
    protected $counter;
    /**
     * @var array
     */
    protected $func;
    /**
     * @var integer
     */
    protected $firstResult;
    /**
     * @var integer
     */
    protected $maxResult;
    /**
     * @var array
     */
    protected $order;

    public function __construct(){
        $this->query=array(
            'select' => "SELECT",
            'where' => "WHERE",
            'from' => "FROM",
            'join' => "",
            'group' => "GROUP BY",
        );
        $this->params = array();
        $this->labels = null;
        $this->values = array();
        $this->valuesConfigs = array();
        $this->counter = 0;
        $this->func = array();
        $this->firstResult = 0;
        $this->maxResult = 0;
        $this->order = array();
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        $q =  $this->query['select'].$this->query['from'].$this->query['join'];
        $q .= (strlen($this->query['where']) > 5)? " ".$this->query['where']:"";
        $q .= (strlen($this->query['group']) > 8)? " ".$this->query['group']:"";
        if($count = count($this->order) > 0){
            $q .= " ORDER BY ";
            $i = 0;
            foreach ($this->order as $prop => $type) {
                if($i > 0 && $count > 1) $q .= ",";
                $q .= $prop." ".$type;
                $i++;
            }
        }
        return $q;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function putParam($key, $value)
    {
        $this->params[$key] = $value;
    }

    /**
     * @param string $entity
     * @param string $alias
     * @return WorkerInterface
     */
    public function setModel($entity, $alias)
    {
        $c = new ModelSetter($entity,$alias);
        $this->query['from'] = $c->updateQuery($this->query['from']);
        return $this;
    }

    /**
     * @param $entity
     * @param $alias
     * @return WorkerInterface
     */
    public function joinModel($entity, $alias)
    {
        $c = new ModelJoiner($entity,$alias);
        $this->query['join'] = $c->updateQuery($this->query['join']);
        return $this;
    }


    /**
     * @param string $property
     * @param string $alias
     * @return WorkerInterface
     */
    public function selector($property, $alias)
    {
        $s = new ValueSelector($property,$alias);
        $this->query['select'] = $s->updateQuery($this->query['select']);
        return $this;
    }


    /**
     * @param string $criteria
     * @param string $name
     * @return WorkerInterface
     */
    public function func($criteria, $name)
    {
        $vm = new ValueMapper($criteria,$name);
        $this->query['select'] = $vm->updateQuery($this->query['select']);
        $this->func[$name] = $vm;
        return $this;
    }

    /**
     * @param string $name
     * @return DataCruncherInterface
     */
    public function values($name)
    {
        return $this->func[$name];
    }

    /**
     * @param string $selector
     * @param array $matches
     * @return DataCruncherInterface
     */
    public function matches($selector, array $matches)
    {
        return new ConditionalValueMapper($selector,$matches);
    }


    /**
     * @param string|array|DataCruncherInterface $labels
     * @return WorkerInterface
     */
    public function labels($labels)
    {
        if(is_string($labels)){
            $val= new ValueMapper($labels,"labels");
            $this->query['select'] = $val->updateQuery($this->query['select']);
            $this->labels = $val;
        }elseif(is_array($labels)){
            $this->labels = new ArrayData($labels);
        }else{
            $this->labels = $labels;
        }
        return $this;
    }

    /**
     * @param string $property
     * @return WorkerInterface
     */
    public function groupBy($property)
    {
        $gb = new GroupBy($property);
        $this->query['group'] = $gb->updateQuery($this->query['group']);
        return $this;
    }

    /**
     * @return Condition
     */
    public function conditions()
    {
        return new Condition($this);
    }

    /**
     * @param Condition $c
     * @return WorkerInterface
     */
    public function applyCondition(Condition $c)
    {
        $this->query['where'] = $c->updateQuery($this->query['where']);
        return $this;
    }

    /**
     * @param mixed|array|DataCruncherInterface $value
     * @param string $label
     * @param array $options
     * @return WorkerInterface
     */
    public function dataset($value, $label, $options = array())
    {
        $id = "dataset_".$this->counter;
        if(is_scalar($value)){
            $value = new ValueMapper($value,$id);
        }elseif(is_array($value)){
            $value = new ArrayData($value);
        }
        if($value instanceof ValueMapper && !isset($this->func[$value->getAlias()])){
            $this->query['select'] = $value->updateQuery($this->query['select']);
        }
        $this->values[$id] = $value;
        $this->valuesConfigs[$id] = array(
            "label" => $label,
            "options" => $options,
        );
        $this->counter++;
        return $this;
    }

    /**
     * @param array $input
     * @return string
     */
    public function getLabel($input)
    {
        return $this->labels->getData($input);
    }

    /**
     * @return array
     */
    public function getValueKeys()
    {
        return array_keys($this->values);
    }

    /**
     * @param string $key
     * @param array $input
     * @return mixed
     */
    public function getData($key, $input)
    {
        return $this->values[$key]->getData($input);
    }

    /**
     * @param string $key
     * @return array
     */
    public function getConfigs($key)
    {
        return $this->valuesConfigs[$key];
    }

    /**
     * @return int
     */
    public function getFirstResult()
    {
        return $this->firstResult;
    }

    /**
     * @param int $firstResult
     * @return WorkerInterface
     */
    public function setFirstResult($firstResult)
    {
        $this->firstResult = $firstResult;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxResult()
    {
        return $this->maxResult;
    }

    /**
     * @param int $maxResult
     * @return WorkerInterface
     */
    public function setMaxResult($maxResult)
    {
        $this->maxResult = $maxResult;
        return $this;
    }

    /**
     * @param array $ordering
     * @return WorkerInterface
     */
    public function order(array $ordering)
    {
        $this->order = $ordering;
        return $this;
    }


} 