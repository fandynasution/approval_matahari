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
use App\Mail\SendPoSMail;
use PDO;
use DateTime;

class PurchaseSelectionController extends Controller
{
    use SendsApprovalMail;

    public function Mail(Request $request)
    {
        if (strpos($request->po_descs, "\n") !== false) {
            $po_descs = str_replace("\n", ' (', $request->po_descs) . ')';
        } else {
            $po_descs = $request->po_descs;
        }

        $list_of_urls = explode('; ', $request->url_file);
        $list_of_files = explode('; ', $request->file_name);
	    $list_of_doc = explode('; ', $request->document_link);

        $url_data = [];
        $file_data = [];
	    $doc_data = [];

        foreach ($list_of_urls as $url) {
            $url_data[] = $url;
        }

        foreach ($list_of_files as $file) {
            $file_data[] = $file;
        }

        foreach ($list_of_doc as $doc) {
            $doc_data[] = $doc;
        }

        $list_of_approve = explode('; ',  $request->approve_exist);
        $approve_data = [];
        foreach ($list_of_approve as $approve) {
            $approve_data[] = $approve;
        }

        $total_amt = number_format($request->total_amt, 2, '.', ',');

        $dataArray = array(
            'ref_no'        => $request->ref_no,
            'po_doc_no'     => $request->po_doc_no,
            'po_descs'      => $po_descs,
            'supplier_name' => $request->supplier_name,
            'sender'        => $request->sender,
            'sender_addr'   => $request->sender_addr,
            'entity_name'   => $request->entity_name,
            'descs'         => $request->descs,
            'user_name'     => $request->user_name,
            'url_file'      => $url_data,
            'file_name'     => $file_data,
	        'doc_link'	    => $doc_data,
            'approve_list'  => $approve_data,
            'curr_cd'       => $request->curr_cd,
            'total_amt'     => $total_amt,
            'clarify_user'  => $request->clarify_user,
            'clarify_email' => $request->clarify_email,
            'body'          => "Please approve Quotation No. ".$request->po_doc_no." for ".$po_descs,
            'subject'       => "Need Approval for Quotation No.  ".$request->po_doc_no,
        );

        $data2Encrypt = array(
            'entity_cd'     => $request->entity_cd,
            'project_no'    => $request->project_no,
            'doc_no'        => $request->doc_no,
            'request_no'    => $request->request_no,
            'trx_date'      => $request->trx_date,
            'level_no'      => $request->level_no,
            'usergroup'     => $request->usergroup,
            'user_id'       => $request->user_id,
            'supervisor'    => $request->supervisor,
            'type'          => 'S',
            'type_module'   => 'PO',
            'text'          => 'Purchase Selection'
        );

        return $this->sendApprovalMail('Purchase Selection', 'send_pos', SendPoSMail::class, [
            'email'       => $request->email_addr,
            'entity_cd'   => $request->entity_cd,
            'doc_no'      => $request->doc_no,
            'level_no'    => $request->level_no,
            'approve_seq' => $request->approve_seq,
            'entity_name' => $request->entity_name,
            'cache_key'   => [$request->approve_seq, $request->entity_cd, $request->doc_no, $request->level_no],
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
                "Pesan" => 'You Have Already Made a Request to ' . $data["text"] . ' No. ' . $data["doc_no"],
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
                "Pesan" => 'There is no ' . $data["text"] . ' with No. ' . $data["doc_no"],
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
        ];

        return view('email/pos/passcheckwithremark2', $dataView);
    }

    public function getaccess(Request $request)
    {
        $data = Crypt::decrypt($request->encrypt);

        $trx_date = rtrim($data["trx_date"], '.');

        // Print the cleaned date string to verify its format

        // First, parse the date using the correct format
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $trx_date);

        $dateTime = $dateTime->format('d-m-Y');

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
        if ($reason=''||$reason=null||$reason=NULL||$reason='null'||$reason='NULL') {
            $reason='0';
        }
        $pdo = DB::connection('matahari')->getPdo();
        $sth = $pdo->prepare("SET NOCOUNT ON; EXEC mgr.x_send_mail_approval_po_selection ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?;");
        $sth->bindParam(1, $data["entity_cd"]);
        $sth->bindParam(2, $data["project_no"]);
        $sth->bindParam(3, $data["doc_no"]);
        $sth->bindParam(4, $data["request_no"]);
        $sth->bindParam(5, $dateTime);
        $sth->bindParam(6, $status);
        $sth->bindParam(7, $data["level_no"]);
        $sth->bindParam(8, $data["usergroup"]);
        $sth->bindParam(9, $data["user_id"]);
        $sth->bindParam(10, $data["supervisor"]);
        $sth->bindParam(11, $reason);
        $sth->execute();
        if ($sth == true) {
            $msg = "You Have Successfully ".$descstatus." the Purchase Selection No. ".$data["doc_no"];
            $notif = $descstatus." !";
            $st = 'OK';
            $image = $imagestatus;
        } else {
            $msg = "You Failed to ".$descstatus." the Purchase Selection No.".$data["doc_no"];
            $notif = 'Fail to '.$descstatus.' !';
            $st = 'OK';
            $image = "reject.png";
        }
        $msg1 = array(
            "Pesan" => $msg,
            "St" => $st,
            "notif" => $notif,
            "image" => $image
        );
        return view("email.after", $msg1);
    }
}
