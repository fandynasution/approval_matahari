<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\SendsApprovalMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use App\Mail\FeedbackMail;
use App\Mail\StaffActionMail;
use App\Mail\StaffActionPoRMail;
use App\Mail\StaffActionPoSMail;
use Carbon\Carbon;

class StaffActionController extends Controller
{
    use SendsApprovalMail;

    public function staffaction(Request $request)
    {
        $callback = array(
            'Error' => false,
            'Pesan' => '',
            'Status' => 200
        );

        $action = ''; // Initialize $action
        $bodyEMail = '';

        if (strcasecmp($request->status, 'R') == 0) {

            $action = 'Revision';
            $bodyEMail = 'Please revise '.$request->descs.' No. '.$request->doc_no.' with the reason : '.$request->reason;

        } else if (strcasecmp($request->status, 'C') == 0){
            
            $action = 'Cancellation';
            $bodyEMail = $request->descs.' No. '.$request->doc_no.' has been cancelled with the reason : '.$request->reason;

        } else if (strcasecmp($request->status, 'A') == 0) {
            $action = 'Approval';
            $bodyEMail = 'Your Request '.$request->descs.' No. '.$request->doc_no.' has been Approved';
        }

        $EmailBack = array(
            'doc_no'            => $request->doc_no,
            'action'            => $action,
            'reason'            => $request->reason,
            'descs'             => $request->descs,
            'subject'		    => $request->subject,
            'bodyEMail'		    => $bodyEMail,
            'user_name'         => $request->user_name,
            'staff_act_send'    => $request->staff_act_send,
            'entity_name'       => $request->entity_name,
            'entity_cd'         => $request->entity_cd,
            'action_date'       => Carbon::now('Asia/Jakarta')->format('d-m-Y H:i')
        );

        return $this->sendFeedbackMail('FeedbackStaffAction', 'Feedback ' . $action, 'feedbackStaffAction', new StaffActionMail($EmailBack), [
            'to'          => $request->email_addr,
            'doc_no'      => $request->doc_no,
            'entity_cd'   => $request->entity_cd,
            'status'      => $request->status,
            'approve_seq' => $request->approve_seq,
        ]);
        
    }

    public function staffaction_por(Request $request)
    {
        $callback = array(
            'Error' => false,
            'Pesan' => '',
            'Status' => 200
        );
        
        $action = ''; // Initialize $action
        $bodyEMail = '';
        
        if (strcasecmp($request->status, 'R') == 0) {
        
            $action = 'Revision';
            $bodyEMail = 'Please revise ' . $request->descs . ' No. ' . $request->doc_no . ' with the reason : ' . $request->reason;
        
        } else if (strcasecmp($request->status, 'C') == 0) {
        
            $action = 'Cancellation';
            $bodyEMail = $request->descs . ' No. ' . $request->doc_no . ' has been cancelled with the reason : ' . $request->reason;
        
        } else if (strcasecmp($request->status, 'A') == 0) {
            $action = 'Approval';
            $bodyEMail = 'Your Request ' . $request->descs . ' No. ' . $request->doc_no . ' has been Approved with the Note : ' . $request->reason;
        }
        
        $list_of_urls = explode('; ', $request->url_file);
        $list_of_files = explode('; ', $request->file_name);
        $list_of_doc = explode('; ', $request->doc_link);
        
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
        
        $EmailBack = array(
            'doc_no'            => $request->doc_no,
            'action'            => $action,
            'reason'            => $request->reason,
            'descs'             => $request->descs,
            'subject'           => $request->subject,
            'bodyEMail'         => $bodyEMail,
            'user_name'         => $request->user_name,
            'staff_act_send'    => $request->staff_act_send,
            'entity_name'       => $request->entity_name,
            'status'            => $request->status,
            'entity_cd'         => $request->entity_cd,
            'url_file'          => $url_data,
            'file_name'         => $file_data,
            'doc_link'          => $doc_data,
            'action_date'       => Carbon::now('Asia/Jakarta')->format('d-m-Y H:i')
        );

        return $this->sendFeedbackMail('FeedbackPOR', 'Feedback ' . $action, 'feedbackPOR', new StaffActionPoRMail($EmailBack), [
            'to'          => $request->email_addr,
            'cc'          => strcasecmp((string) $request->status, 'A') == 0 ? $request->email_cc : null,
            'doc_no'      => $request->doc_no,
            'entity_cd'   => $request->entity_cd,
            'status'      => $request->status,
            'approve_seq' => $request->approve_seq,
            'cache_date'  => $request->date_approved,
        ]);              
    }

    public function staffaction_pos(Request $request)
    {
        $callback = array(
            'Error' => false,
            'Pesan' => '',
            'Status' => 200
        );

        $action = ''; // Initialize $action
        $bodyEMail = '';

        if (strcasecmp($request->status, 'R') == 0) {

            $action = 'Revision';
            $bodyEMail = 'Please revise '.$request->descs.' No. '.$request->doc_no.' with the reason : '.$request->reason;

        } else if (strcasecmp($request->status, 'C') == 0){
            
            $action = 'Cancellation';
            $bodyEMail = $request->descs.' No. '.$request->doc_no.' has been cancelled with the reason : '.$request->reason;

        } else if (strcasecmp($request->status, 'A') == 0) {
            $action = 'Approval';
            $bodyEMail = 'Your Request '.$request->descs.' No. '.$request->doc_no.' has been Approved';
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

        $EmailBack = array(
            'doc_no'            => $request->doc_no,
            'action'            => $action,
            'reason'            => $request->reason,
            'descs'             => $request->descs,
            'subject'		    => $request->subject,
            'bodyEMail'		    => $bodyEMail,
            'user_name'         => $request->user_name,
            'staff_act_send'    => $request->staff_act_send,
            'entity_name'       => $request->entity_name,
            'entity_cd'         => $request->entity_cd,
            'url_file'          => $url_data,
            'file_name'         => $file_data,
            'action_date'       => Carbon::now('Asia/Jakarta')->format('d-m-Y H:i')
        );

        return $this->sendFeedbackMail('FeedbackPOS', 'Feedback ' . $action, 'feedbackPOS', new StaffActionPoSMail($EmailBack), [
            'to'          => $request->email_addr,
            'doc_no'      => $request->doc_no,
            'entity_cd'   => $request->entity_cd,
            'status'      => $request->status,
            'approve_seq' => $request->approve_seq,
        ]);
              
    }

    public function fileexist(Request $request)
    {
        $file_name = $request->file_name;
        $folder_name = $request->folder_name;

        // Connect to FTP server
        $ftp_server = "10.112.1.212";
        $ftp_conn = ftp_connect($ftp_server, 2121) or die("Could not connect to $ftp_server");

        // Log in to FTP server
        $ftp_user_name = "ifca";
        $ftp_user_pass = "3pGRCPdEBXa72NWc";
        $login = ftp_login($ftp_conn, $ftp_user_name, $ftp_user_pass);

        $file = "public/ifca-att/".$folder_name."/".$file_name;


        if (ftp_size($ftp_conn, $file) > 0) {
            echo "Ada File";
        } else {
            echo "Tidak Ada File";
        }

        ftp_close($ftp_conn);
    }
}
