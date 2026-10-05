@if($warnings !== [])<div class="rounded-sm bg-warning/10 p-3 text-xs"><ul class="list-disc space-y-1 pl-4">@foreach($warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif
