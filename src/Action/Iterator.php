<?php

namespace atk4\data\Action;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Model\Scope\AbstractScope;
use atk4\data\Model\Scope\Condition;
use atk4\data\Model\Scope\Scope;

/**
 * Class Array_ is returned by $model->action(). Compatible with DSQL to a certain point as it implements
 * specific actions such as getOne() or get().
 */
class Iterator
{
    /**
     * @var \ArrayIterator
     */
    public $generator;

    /**
     * Iterator constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->generator = new \ArrayIterator($data);
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function filter(AbstractScope $scope)
    {
        if (!$scope->isEmpty()) {
            $this->generator = new \CallbackFilterIterator($this->generator, function ($row) use ($scope) {
                return $this->match($row, $scope);
            });
        }

        return $this;
    }

    /**
     * Calculates SUM|AVG|MIN|MAX aggragate values for $field.
     *
     * @param string $fx
     * @param string $field
     * @param bool   $coalesce
     *
     * @throws Exception
     *
     * @return \atk4\data\Action\Iterator
     */
    public function aggregate($fx, $field, $coalesce = false)
    {
        $result = 0;
        $column = array_column($this->get(), $field);

        switch (strtoupper($fx)) {
            case 'SUM':
                $result = array_sum($column);
            break;

            case 'AVG':
                $column = $coalesce ? $column : array_filter($column, function ($value) {
                    return !is_null($value);
                });

                $result = array_sum($column) / count($column);
            break;

            case 'MAX':
                $result = max($column);
            break;

            case 'MIN':
                $result = min($column);
            break;

            default:
                throw new Exception([
                    'Persistence\Array_ driver action unsupported format',
                    'action'    => $fx,
                ]);
        }

        $this->generator = new \ArrayIterator([[$result]]);

        return $this;
    }

    /**
     * Checks if $row matches $scope.
     *
     * @param array         $row
     * @param AbstractScope $scope
     *
     * @throws Exception
     *
     * @return bool
     */
    protected function match(array $row, AbstractScope $scope)
    {
        $match = false;

        // simple condition
        if ($scope instanceof Condition) {
            $args = $scope->toArray();

            $field = $args[0];
            $operator = $args[1] ?? null;
            $value = $args[2] ?? null;
            if (count($args) == 2) {
                $value = $operator;

                $operator = '=';
            }

            if (!is_a($field, Field::class)) {
                throw new Exception([
                    'Persistence\Array_ driver condition unsupported format',
                    'reason'    => 'Unsupported object instance '.get_class($field),
                    'condition' => $scope,
                ]);
            }

            if (isset($row[$field->short_name])) {
                $match = $this->where($row[$field->short_name], $operator, $value);
            }
        }

        // nested conditions
        if ($scope instanceof Scope) {
            $matches = [];

            foreach ($scope->getActiveComponents() as $component) {
                $matches[] = $subMatch = (bool) $this->match($row, $component);

                // do not check all conditions if any match required
                if ($scope->any() && $subMatch) {
                    break;
                }
            }

            // any matches && all matches the same (if all required)
            $match = array_filter($matches) && ($scope->all() ? count(array_unique($matches)) === 1 : true);
        }

        return $match;
    }

    protected function where($v1, $operator, $v2)
    {
        switch (strtoupper($operator)) {
            case '=':
                $result = is_array($v2) ? $this->where($v1, 'IN', $v2) : $v1 == $v2;
            break;

            case '>':
                $result = $v1 > $v2;
            break;

            case '>=':
                $result = $v1 >= $v2;
            break;

            case '<':
                $result = $v1 < $v2;
            break;

            case '<=':
                $result = $v1 <= $v2;
            break;

            case '!=':
            case '<>':
                $result = !$this->where($v1, '=', $v2);
            break;

            case 'LIKE':
                $pattern = str_ireplace('%', '(.*?)', preg_quote($v2));

                $result = preg_match('/^'.$pattern.'$/', $v1);
            break;

            case 'NOT LIKE':
                $result = !$this->where($v1, 'LIKE', $v2);
            break;

            case 'IN':
                $result = is_array($v2) ? in_array($v1, $v2) : $this->where($v1, '=', $v2);
            break;

            case 'NOT IN':
                $result = !$this->where($v1, 'IN', $v2);
            break;

            case 'REGEXP':
                $result = preg_match('/'.$v2.'/', $v1);
            break;

            case 'NOT REGEXP':
                $result = !$this->where($v1, 'REGEXP', $v2);
            break;

            default:
                throw new Exception([
                    'Unsupported operator',
                    'operator'    => $operator,
                ]);
        }

        return $result;
    }

    /**
     * Applies sorting on Iterator.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function order($fields)
    {
        $data = $this->get();

        // prepare arguments for array_multisort()
        $args = [];
        foreach ($fields as list($field, $desc)) {
            $args[] = array_column($data, $field);
            $args[] = $desc ? SORT_DESC : SORT_ASC;
        }
        $args[] = &$data;

        // call sorting
        call_user_func_array('array_multisort', $args);

        // put data back in generator
        $this->generator = new \ArrayIterator(array_pop($args));

        return $this;
    }

    /**
     * Limit Iterator.
     *
     * @param int $length
     * @param int $offset
     *
     * @return $this
     */
    public function limit($length, $offset = 0)
    {
        $data = array_slice($this->get(), $offset, $length, true);

        // put data back in generator
        $this->generator = new \ArrayIterator($data);

        return $this;
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     *
     * @return $this
     */
    public function count()
    {
        $this->generator = new \ArrayIterator([[iterator_count($this->generator)]]);

        return $this;
    }

    /**
     * Return all data inside array.
     *
     * @return array
     */
    public function get()
    {
        return iterator_to_array($this->generator, true);
    }

    /**
     * Return one row of data.
     *
     * @return array
     */
    public function getRow()
    {
        $row = $this->generator->current();
        $this->generator->next();

        return $row;
    }

    /**
     * Return one value from one row of data.
     *
     * @return mixed
     */
    public function getOne()
    {
        $data = $this->getRow();

        return array_shift($data);
    }
}
