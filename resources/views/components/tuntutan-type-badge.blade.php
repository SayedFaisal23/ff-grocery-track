@props(['claim'])

@if($claim->tag === 'Pantry')
    <span class="badge badge-primary"><i class="fa-solid fa-boxes-stacked"></i> Pantry</span>
@elseif($claim->tag === 'General')
    <span class="badge badge-general"><i class="fa-solid fa-folder-open"></i> General</span>
@else
    <span class="badge badge-success"><i class="fa-solid fa-utensils"></i> Lunch</span>
@endif
