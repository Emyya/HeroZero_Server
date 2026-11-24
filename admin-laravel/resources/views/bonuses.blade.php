@extends('layout')
@section('title', 'Dias de Bónus')
@section('content')
<h2>Dias de Bónus</h2>
<p>Relógio do jogo: <strong>{{ $now }}</strong>. As datas abaixo seguem este relógio.</p>
@if($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
<div class="card" style="margin-top:16px">
<form method="post" action="/bonuses">@csrf
<div class="grid">
<div><label>Nome do evento</label><input name="name" maxlength="100" required value="{{ old('name', 'Dias de Bónus') }}"></div>
<div><label>Primeiro dia</label><input type="date" name="start" min="{{ $today }}" value="{{ old('start', $today) }}" required></div>
<div><label>Duração em dias de calendário</label><input type="number" name="duration" min="1" max="365" value="{{ old('duration', 3) }}" required></div>
<div><label>Multiplicador</label><select name="multiplier">@foreach([2,3,4,5] as $m)<option value="{{ $m }}" @selected(old('multiplier',2)==$m)>×{{ $m }}</option>@endforeach</select></div>
</div>
<h3>Vantagens combináveis</h3>
@foreach($types as $id => $name)
<label style="margin-bottom:8px"><input type="checkbox" name="types[]" value="{{ $id }}" style="width:auto" @checked(in_array($id, old('types', [7,8])))> {{ $name }}</label>
@endforeach
<p style="margin:12px 0">O primeiro dia conta na duração. XP e moedas aplicam-se às missões resgatadas durante o evento. Energia e treinos aumentam o limite e concedem o acréscimo uma vez por dia; energia de treino aumenta a capacidade da barra.</p>
<button>Criar evento</button>
</form>
</div>
<table><thead><tr><th>Evento</th><th>Datas</th><th>Vantagens</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($events as $event)
<tr><td>{{ $event->name }}</td><td>{{ $event->start_date }} → {{ $event->end_date }}</td>
<td>@foreach(($days[$event->id] ?? collect())->unique('bonus_type') as $day)<div>{{ $types[$day->bonus_type] ?? $day->bonus_type }} ×{{ $day->multiplier }}</div>@endforeach</td>
<td>{{ !$event->enabled ? 'Cancelado' : ($event->end_date < $today ? 'Terminado' : ($event->start_date > $today ? 'Agendado' : 'Activo')) }}</td>
<td>@if($event->enabled && $event->end_date >= $today)<form method="post" action="/bonuses/{{ $event->id }}/cancel">@csrf<button class="danger">Cancelar</button></form>@endif</td></tr>
@empty<tr><td colspan="5">Não existem eventos.</td></tr>@endforelse
</tbody></table>
@endsection
