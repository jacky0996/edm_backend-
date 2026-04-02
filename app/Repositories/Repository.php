<?php

namespace App\Repositories;

/**
 * Class Repository
 * 所有 Repository 的基底抽象類別
 */
abstract class Repository
{
    use RepositoryTrait;

    /**
     * 所屬的 Model 實例
     * @var mixed
     */
    protected $model;

    /**
     * 取得當前 Model
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }
}
