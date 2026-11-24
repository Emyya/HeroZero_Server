<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusController extends Controller
{
    private function boot(): void
    {
        // Partilhar calendário e nomes com o jogo; ligação SQL continua a ser a do admin.
        foreach (['GameTime', 'Live', 'BonusEvents'] as $class) {
            require_once base_path('../server-laravel/app/HeroZero/'.$class.'.php');
        }
    }

    public function index()
    {
        $this->boot();
        return view('bonuses', ['events' => DB::table('bonus_events')->orderByDesc('id')->get(),
            'days' => DB::table('bonus_event_days')->get()->groupBy('event_id'),
            'types' => \HeroZero\BonusEvents::TYPES, 'today' => \HeroZero\BonusEvents::today(),
            'now' => \HeroZero\Live::gameNow()->format('d/m/Y H:i')]);
    }

    public function store(Request $request)
    {
        $this->boot();
        $input = $request->validate(['name' => 'required|string|max:100', 'start' => 'required|date_format:Y-m-d',
            'duration' => 'required|integer|min:1|max:365', 'multiplier' => 'required|integer|min:2|max:5',
            'types' => 'required|array|min:1', 'types.*' => 'required|integer|in:'.implode(',', array_keys(\HeroZero\BonusEvents::TYPES))]);
        if ($input['start'] < \HeroZero\BonusEvents::today()) return back()->with('error', 'O início deve ser hoje ou uma data futura do relógio do jogo.');
        $dates = \HeroZero\BonusEvents::calendar($input['start'], (int)$input['duration']);
        try {
            DB::transaction(function () use ($input, $dates): void {
                $id = DB::table('bonus_events')->insertGetId(['name' => trim($input['name']),
                    'start_date' => $input['start'], 'end_date' => end($dates), 'enabled' => 1]);
                foreach ($dates as $date) foreach (array_unique($input['types']) as $type) {
                    DB::table('bonus_event_days')->insert(['event_id' => $id, 'bonus_date' => $date,
                        'bonus_type' => (int)$type, 'multiplier' => (int)$input['multiplier']]);
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            return back()->with('error', 'Já existe um evento com a mesma vantagem numa das datas escolhidas.');
        }
        return redirect('/bonuses')->with('ok', 'Evento criado. O jogo recebe os bónus na próxima sincronização.');
    }

    public function cancel(int $id)
    {
        DB::transaction(function () use ($id): void {
            abort_unless(DB::table('bonus_events')->where('id', $id)->lockForUpdate()->first(), 404);
            DB::table('bonus_event_days')->where('event_id', $id)->delete();
            DB::table('bonus_events')->where('id', $id)->update(['enabled' => 0]);
        });
        return redirect('/bonuses')->with('ok', 'Evento cancelado.');
    }
}
