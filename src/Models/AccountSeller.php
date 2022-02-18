<?php

namespace Vega\Connector\Vtex\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Vega\Core\Models\Traits\Eloquent\Updatable;
use Vega\Integrations\Models\Account;

/**
 * Class AccountSeller
 * @package Vega\Connector\Models
 */
class AccountSeller extends Eloquent
{
    use Updatable;

    /**
     * @var string
     */
    protected $table = 'account_sellers';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'account_id',
        'appkey',
        'apptoken',
        'site',
        'is_active'
    ];

    /**
     * @var array
     */
    protected $validationRules = [
        'name' => 'max:60|required',
        'code' => 'max:60|required',
        'account_id' => 'required',
        'appkey' => 'max:100|required',
        'apptoken' => 'max:132|required',
        'site' => 'max:60',
        'is_active' => 'required'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * Scope a query to only include active users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
