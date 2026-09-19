<?php

namespace App\Modules\Branding\Contracts;

use App\Models\Company;
use Illuminate\Http\Request;

interface CurrentCompanyResolver
{
    public function resolve(Request $request): Company;
}
