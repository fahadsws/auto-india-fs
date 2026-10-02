<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantFeedback extends Model
{
    protected $table = 'assistant_feedback';
    protected $guarded = [];

    public function session() { return $this->belongsTo(AssistantSession::class, 'assistant_session_id'); }
}
