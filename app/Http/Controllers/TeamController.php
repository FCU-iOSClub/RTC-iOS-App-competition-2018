<?php

namespace App\Http\Controllers;

use App\Notifications\PorposalUpload;
use App\TeamMember;
use App\Univercity;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Notification;
use Setting;
use Storage;

class TeamController extends Controller
{
    /**
     * Create a new controller instance and set middleware.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('is.verify');
        $this->middleware('can.edit.teammate');
        $this->middleware('deadline.register')->only('rename');
    }

    /**
     * render team info view.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $teamMember = $user->team_member()->orderBy('level')->get();
        $data = $teamMember->pluck('level');
        for ($i = 1; $i < 6; $i++) {
            if (!$data->contains($i)) {
                $tmp = new TeamMember(['level'=>$i]);
                $teamMember->push($tmp);
            }
        }

        return view('team.info', ['team'=>$teamMember]);
    }

    /**
     * render edit team member view.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($level)
    {
        if ((((int) $level) > 5 || ((int) $level) < 1)) {
            return back();
        }

        return view('team.edit', ['level'=>$level]);
    }

    /**
     * update team member data.
     *
     * @return \Illuminate\Http\Response
     */
    public function update($level)
    {
        if ((((int) $level) > 5 || ((int) $level) < 1)) {
            return back();
        }
        $user = auth()->user();
        $request = request();
        $email = $request->input('email');

        if ($this->isDuplicationEmail($email, $level)) {
            return redirect()->back()->withErrors(['error'=>"此電子郵件 $email 已被其他隊伍使用！"]);
        }
        $univercity = Univercity::where('name', request()->input('univercity'))->where('course', request()->input('course'))->first();

        if (!$univercity) {
            return redirect()->back()->withErrors(['error'=>'學校 / 學系不存在！']);
        }

        $request->validate([
            'name'  => 'required|max:255',
            'phone' => 'required|phone:TW',
            'email' => 'required|email',
        ]);

        $data = collect([
            'name' => $request->input('name'),
            'email'=> $email,
            'phone'=> $request->input('phone'),
        ]);
        $data->put('univercity_id', $univercity->id);
        $team_member = TeamMember::updateOrCreate([
            'level'  => $level,
            'user_id'=> $user->id,
        ], $data->toArray());

        return redirect()->route('team.info');
    }

    /**
     * Get team member data.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTeamData($level)
    {
        $user = auth()->user();
        $teamMember = $user->team_member;
        $target = $teamMember->where('level', $level)->first();
        if ($target) {
            $target->univercity;
        }

        return ['result'=>$target];
    }

    /**
     * Get team member data.
     *
     * @return \Illuminate\Http\Response
     */
    public function clear($level)
    {
        $user = auth()->user();
        $teamMember = $user->team_member;
        $target = $teamMember->where('level', $level)->first();
        if ($target) {
            $target->delete();
        }

        return back();
    }

    /**
     * Rename team.
     *
     * @return \Illuminate\Http\Response
     */
    public function rename(Request $request)
    {
        $request->validate([
                   'name'     => 'required|string|max:255|unique:users',
               ]);
        $user = auth()->user();
        $user->name = $request->input('name');
        $user->save();

        return back();
    }

    /**
     * Check email duplication response.
     *
     * @return \Illuminate\Http\Response
     */
    public function checkEmailDuplication($level)
    {
        $email = request()->email;
        if (!$email) {
            return [];
        }

        return ['result'=>$this->isDuplicationEmail($email, $level)];
    }

    /**
     * check email is unique or duplication.
     *
     * @param string $email
     * @param string $level
     *
     * @return bool return isDuplication
     */
    private function isDuplicationEmail($email, $level)
    {
        $user = auth()->user();

        $isDuplicationMember = TeamMember::whereEmail($email)->first();

        if ((int) $level > 3) {
            $isDuplicationMember = TeamMember::whereEmail($email)->where('level', '<>', 4)->where('level', '<>', 5)->first();
        }

        $isSameMember = TeamMember::whereEmail($email)->where('user_id', $user->id)->where('level', $level)->first();

        if ($isDuplicationMember != null && !$isSameMember) {
            return true;
        }

        $isDuplicationTeamEmail = User::role('participant')->where('id', '<>', $user->id)->where('email', $email)->first();
        if ($isDuplicationTeamEmail) {
            return true;
        }

        return false;
    }

    /**
     * return proposal manage view.
     *
     * @return \Illuminate\Http\Response
     */
    public function proposalUploadView()
    {
        return view('team.proposalUpload');
    }

    /**
     * upload proposal.
     *
     * @return \Illuminate\Http\Response
     */
    public function proposalUploadFile(Request $request)
    {
        if (
            !auth()->user()->hasRole('developer')
            && Carbon::now()->gt(Carbon::parse(Setting::get('proposal_deadline', '2018-5-21'), 'Asia/Taipei'))
        ) {
            return redirect()->back()->withErrors(['msg'=>'不在開放時間內！']);
        } else {
            $request->validate([
                'proposal' => 'required|mimes:pdf|mimetypes:application/pdf|max:10240',
            ]);
            $request->proposal->storeAs(auth()->user()->id, 'proposal.pdf');
            Notification::route('mail', 'ahkui@mail.fcu.edu.tw')
                        ->notify(new PorposalUpload(auth()->user()));

            return redirect()->back()->with('msg', '成功上傳！');
        }
    }

    /**
     * download proposal.
     *
     * @return \Illuminate\Http\Response
     */
    public function proposalDownload()
    {
        if (Storage::exists(auth()->user()->id.'/proposal.pdf')) {
            return response(Storage::get(auth()->user()->id.'/proposal.pdf'))->withHeaders([
                    'Content-Type'        => 'application/pdf',
                    'Cache-Control'       => 'no-store, no-cache',
                    'Content-Disposition' => 'attachment; filename="'.auth()->user()->name.'_企劃書_'.Carbon::now()->toDateTimeString().'.pdf"',
                ]);
        }

        return redirect()->back()->withErrors(['msg'=>'檔案不存在']);
    }

    /**
     * return register form manage view.
     *
     * @return \Illuminate\Http\Response
     */
    public function registerFormUploadView()
    {
        return view('team.registerFormUpload');
    }

    /**
     * return app manage view.
     *
     * @return \Illuminate\Http\Response
     */
    public function appUploadView()
    {
        return view('team.appUpload');
    }
}
