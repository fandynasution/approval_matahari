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
use App\Mail\FeedbackPLLymanMail;
use App\Mail\FeedbackPLRevisionMail;
use Carbon\Carbon;

class FeedbackPLController extends Controller
{
    use SendsApprovalMail;

    public function feedbackbudgetlyman(Request $request) 
    {
        $action = ''; // Initialize $action
        $bodyEMail = '';

        if (strcasecmp($request->status, 'R') == 0) {

            $action = 'Revision';
            $bodyEMail = 'Please revise RAB Budget No. '.$request->doc_no;

        } else if (strcasecmp($request->status, 'C') == 0){
            
            $action = 'Cancellation';
            $bodyEMail = 'RAB Budget No. ' . $request->doc_no . ' has been cancelled with the reason : ';

        } else if (strcasecmp($request->status, 'A') == 0) {
            $action = 'Approval';
            $bodyEMail = 'Your Request RAB Budget No. '.$request->doc_no.' has been Approved';
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

        return $this->sendFeedbackMail('FeedbackPLBudgetLyman', 'Feedback ' . $action, 'feedbackPLBudgetLyman', new FeedbackPLLymanMail($EmailBack), [
            'to'          => $request->email_addr,
            'doc_no'      => $request->doc_no,
            'entity_cd'   => $request->entity_cd,
            'status'      => $request->status,
            'approve_seq' => $request->approve_seq,
        ]);
    }

    public function feedbackbudgetrevision(Request $request) 
    {
        $action = ''; // Initialize $action
        $bodyEMail = '';

        if (strcasecmp($request->status, 'R') == 0) {

            $action = 'Revision';
            $bodyEMail = 'Please revise '.$request->descs.' No. '.$request->doc_no;

        } else if (strcasecmp($request->status, 'C') == 0){
            
            $action = 'Cancellation';
            $bodyEMail = $request->descs.' No. '.$request->doc_no;

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

        return $this->sendFeedbackMail('FeedbackPLBudgetRevision', 'Feedback ' . $action, 'feedbackPLBudgetRevision', new FeedbackPLRevisionMail($EmailBack), [
            'to'          => $request->email_addr,
            'doc_no'      => $request->doc_no,
            'entity_cd'   => $request->entity_cd,
            'status'      => $request->status,
            'approve_seq' => $request->approve_seq,
        ]);
    }
}
