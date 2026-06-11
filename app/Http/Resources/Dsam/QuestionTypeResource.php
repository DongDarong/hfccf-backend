<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'display_name'    => $this->display_name,
            'display_name_kh' => $this->display_name_kh,
            'icon'            => $this->icon,
            'has_options'     => $this->has_options,
            'has_scoring'     => $this->has_scoring,
            'config_schema'   => $this->config_schema,
            'sort_order'      => $this->sort_order,
        ];
    }
}
