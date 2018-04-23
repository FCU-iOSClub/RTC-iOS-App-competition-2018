<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\User;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\TeamMember;

class TeamListExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $data = User::role('participant')->with('team_member')->get();
        $alldata = collect();
        // dd($data->all());
        
        foreach ($data as $teamkey => $teamvalue) {
            $teamdata = collect([
                'created_at'=>$teamvalue->created_at->toDateTimeString(),
                'team_name'=>$teamvalue->name,
                'team_email'=>$teamvalue->email,
                'split'=>'',
            ]);
            for ($i=1;$i<6;$i++) {
                $value = $teamvalue->team_member()->whereLevel($i)->first();
                if ($value)
                    $teamdata->push([
                        'level'=>TeamMember::levelText($value->level),
                        'school'=>$value->univercity->name,
                        'course'=>$value->univercity->course,
                        'name'=>$value->name,
                        'email'=>$value->email,
                        'phone'=>$value->phone,
                        'split'=>'',
                    ]);
                else
                    $teamdata->push([
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                    ]);
            }
            $alldata->push($teamdata->flatten());
        }
        // dd($alldata->toArray());

        return $alldata;
    }

    public function headings(): array
    {
        return [
            '建立時間',
            '隊伍名稱',
            '隊伍電子郵件',
            '',
            '職位',
            '學校',
            '系所',
            '姓名',
            '隊員 Email',
            '手機號碼',
            '',
            '職位',
            '學校',
            '系所',
            '姓名',
            '隊員 Email',
            '手機號碼',
            '',
            '職位',
            '學校',
            '系所',
            '姓名',
            '隊員 Email',
            '手機號碼',
            '',
            '職位',
            '學校',
            '系所',
            '姓名',
            '隊員 Email',
            '手機號碼',
            '',
            '職位',
            '學校',
            '系所',
            '姓名',
            '隊員 Email',
            '手機號碼',
            '',
        ];
    }
}