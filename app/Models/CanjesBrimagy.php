<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CanjesBrimagy extends Model
{
    use HasFactory;
    protected $connection = 'mysql_brimagy';
    protected $table = 'swaps_view';
    protected $fillable = [
        'user_id',
        'folio',
        'available_period_id',
        'status',
        'created_at',
        'responsable_for_the_swap',
        'representative_id',
        'name',
        'first_last_name',
        'second_last_name',
        'send_to',
        'api_id',
        'phone',
        'email',
        'points_swap',
        'rel_id',
        'number_of_awards',
        'size',
        'color',
        'category',
        'sub_category',
        'desc',
        'required_score',
        'sku',
        'award_id',
        'state',
        'street',
        'number',
        'colony',
        'poastal_code',
        'municipality',
        'inside',
        'between_1',
        'between_2',
        'additional_reference',
    ];

}
