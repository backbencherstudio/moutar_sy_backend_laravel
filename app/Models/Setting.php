<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'site_name',
        'site_logo',
        'admin_logo',
        'favicon',
        'seo_meta_description',
        'seo_keywords',
        'seo_image',
        'app_mode',
        'login_with_facebook',
        'copyright_text',
        'facebook_url',
        'youtube_url',
        'twitter_url',
        'linkedin_url',
        'telegram_url',
        'instagram_url',
        'map_link',
        'email',
        'whatsapp_number',
        'phone_no',
        'support_email',
        'address',
        'default_shipping_cost',
        'default_payment_fee',
        'default_vat',
    ];
}
