<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TeamPaymentDefault
 * php version 7.1
 *
 * @category App\Models
 * @package  App\Models
 * @author   Joseph Montanez <jm@comentum.com>
 * @license  https://opensource.org/licenses/BSD-3-Clause BSD
 * @link     https://sportstravelhq.com
 *
 * @property int id
 * @property int user_id
 * @property int team_id
 * @property string first_name
 * @property string last_name
 * @property string email
 * @property string phone
 * @property \Carbon\Carbon created_at
 * @property \Carbon\Carbon updated_at
 */
class TeamPaymentDefault extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'user_id', 'team_id', 'created_at', 'updated_at'];


    /**
     * Gets the user that the defaults belong to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Gets the team that the defaults belong to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
