<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Concerns\SendsApprovalMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Mail\SendPLLymanMail;
use PDO;
use DateTime;

class PLBudgetLymanController extends Controller
{
    use SendsApprovalMail;

    public function Mail(Request $request)
    {
        $amount = number_format( $request->amount , 2 , '.' , ',' );

        $list_of_approve = explode('; ',  $request->approve_exist);
        $approve_data = [];
        foreach ($list_of_approve as $approve) {
            $approve_data[] = $approve;
        }

        $list_of_urls = explode('; ', $request->url_file);
        $list_of_files = explode('; ', $request->file_name);

        $url_data = [];
        $file_data = [];

        foreach ($list_of_urls as $url) {
            $url_data[] = $url;
        }

        foreach ($list_of_files as $file) {
            $file_data[] = $file;
        }

        $dataArray = array(
            'descs'         => $request->descs,
            'entity_name'   => $request->entity_name,
            'project_name'  => $request->project_name,
            'amount'        => $amount,
            'doc_no'        => $request->doc_no,
            'user_name'     => $request->user_name,
            'sender'        => $request->sender,
            'module'        => $request->module,
            'url_file'      => $url_data,
            'file_name'     => $file_data,
            'approve_list'  => $approve_data,
            'clarify_user'  => $request->clarify_user,
            'clarify_email' => $request->clarify_email,
            'sender_addr'   => $request->sender_addr,
            'body'          => "Please approve RAB Budget No. ".$request->doc_no." project ".$request->project_name. " with Amount ".$amount,
            'subject'       => "Need Approval for RAB Budget No. ".$request->doc_no,
        );

        $data2Encrypt = array(
            'entity_cd'     => $request->entity_cd,
            'project_no'    => $request->project_no,
            'email_address' => $request->email_addr,
            'entity_name'   => $request->entity_name,
            'level_no'      => $request->level_no,
            'doc_no'        => $request->doc_no,
            'user_id'       => $request->user_id,
            'type'          => 'X',
            'type_module'   => 'PL',
            'text'          => 'Budget Lyman'
        );

        return $this->sendApprovalMail('RAB Budget', 'send_pl_budget_lyman', SendPLLymanMail::class, [
            'email'       => $request->email_addr,
            'entity_cd'   => $request->entity_cd,
            'doc_no'      => $request->doc_no,
            'level_no'    => $request->level_no,
            'approve_seq' => $request->approve_seq,
            'entity_name' => $request->entity_name,
            'cache_key'   => [$request->approve_seq, $request->entity_cd, $request->doc_no, $request->status, $request->level_no],
        ], $dataArray, $data2Encrypt);
    }

    public function processData($status='', $encrypt='')
    {

        $data = Crypt::decrypt($encrypt);
        Log::info('Decrypted data: ' . json_encode($data));

        $where = [
            'doc_no'        => $data["doc_no"],
            'entity_cd'     => $data["entity_cd"],
            'level_no'      => $data["level_no"],
            'type'          => $data["type"],
            'module'        => $data["type_module"],
        ];

        $exists = DB::connection('matahari')
        ->table('mgr.cb_cash_request_appr')
        ->where($where)
        ->whereIn('status', ["A", "R", "C"])
        ->exists();

        if ($exists) {
            $msg1 = [
                "Pesan" => 'You Have Already Made a Request to RAB Budget No. '.$data["doc_no"],
                "St" => 'OK',
                "notif" => 'Restricted !',
                "image" => "double_approve.png"
            ];
            return view("email.after", $msg1);
        }

        $where2 = array_merge($where, ['status' => 'P']);
        

        $exists2 = DB::connection('matahari')
            ->table('mgr.cb_cash_request_appr')
            ->where($where2)
            ->exists();

        if (!$exists2) {
            $msg1 = [
                "Pesan" => 'There is no RAB Budget with No. '.$data["doc_no"],
                "St" => 'OK',
                "notif" => 'Restricted !',
                "image" => "double_approve.png"
            ];
            return view("email.after", $msg1);
        }

        // Tentukan status dan parameter untuk tampilan
        $statusOptions = [
            'A' => ['Approval', '#40de1d', 'Approve'],
            'R' => ['Revision', '#f4bd0e', 'Revise'],
            'C' => ['Cancellation', '#e85347', 'Cancel']
        ];

        $statusData = $statusOptions[$status] ?? $statusOptions['C'];

        $dataView = [
            "status"        => $status,
            "encrypt"       => $encrypt,
            "name"          => $statusData[0],
            "bgcolor"       => $statusData[1],
            "valuebt"       => $statusData[2],
            "entity_name"   => $data["entity_name"]
        ];

        return view('email/plbudget/passcheckwithremark', $dataView);
    }

    public function update(Request $request)
    {
        $data = Crypt::decrypt($request->encrypt);

        $status = $request->status;

        $reasonget = $request->reason;

        $descstatus = " ";
        $imagestatus = " ";

        $msg = " ";
        $msg1 = " ";
        $notif = " ";
        $st = " ";
        $image = " ";

        if ($reasonget == '' || $reasonget == NULL) {
            $reason = '0';
        } else {
            $reason = $reasonget;
        }

        if ($status == "A") {
            $descstatus = "Approved";
            $imagestatus = "approved.png";
        } else if ($status == "R") {
            $descstatus = "Revised";
            $imagestatus = "revise.png";
        } else {
            $descstatus = "Cancelled";
            $imagestatus = "reject.png";
        }
        $pdo = DB::connection('matahari')->getPdo();
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.xrl_send_mail_approval_pl_budget_lyman ?, ?, ?, ?, ?, ?;");
        $sth->bindParam(1, $data["entity_cd"]);
        $sth->bindParam(2, $data["project_no"]);
        $sth->bindParam(3, $data["doc_no"]);
        $sth->bindParam(4, $status);
        $sth->bindParam(5, $data["level_no"]);
        $sth->bindParam(6, $data["user_id"]);
        $sth->execute();
        if ($sth == true) {
            $msg = "You Have Successfully ".$descstatus." the RAB Budget No. ".$data["doc_no"];
            $notif = $descstatus." !";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You Failed to ".$descstatus." the RAB Budget No.".$data["doc_no"];
            $notif = 'Fail to '.$descstatus.' !';
            $st = 'OK';
            $image = "reject.png";
        }
        $msg1 = array(
            "Pesan" => $msg,
            "St" => $st,
            "notif" => $notif,
            "image" => $image,
            "entity_name"   => $data["entity_name"]
        );
        return view("email.after", $msg1);
    }
}