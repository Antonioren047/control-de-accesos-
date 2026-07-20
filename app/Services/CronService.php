<?php
declare(strict_types=1);
namespace Vigilancia\Services;
use Vigilancia\Repositories\MaintenanceRepository;

final class CronService
{
    public function __construct(private MaintenanceRepository$repo,private string$root){}
    public function run():array{
        if(!$this->repo->lock())return['locked'=>true,'message'=>'Ya existe una ejecución en curso.'];
        $results=[];
        try{
            $tasks=[
                'calculate_absences'=>fn()=>['created'=>$this->absences()],
                'close_incomplete_rounds'=>fn()=>['updated'=>$this->repo->closeIncompleteRounds()],
                'pending_supervisions'=>fn()=>['pending'=>$this->repo->pendingSupervisions()],
                'expire_qr'=>fn()=>['expired'=>$this->repo->expireQr()],
                'storage_alerts'=>fn()=>['alerts'=>$this->repo->alertStorage($this->repo->setting('storage.warning_percent',90))],
                'retention'=>fn()=>$this->retention(),
                'temporary_cleanup'=>fn()=>['deleted'=>$this->cleanup()],
            ];
            foreach($tasks as$name=>$task){$id=$this->repo->start($name);$start=microtime(true);try{$value=$task();$this->repo->finish($id,'success',$value,null,(int)((microtime(true)-$start)*1000));$results[$name]=$value;}catch(\Throwable$e){$this->repo->finish($id,'error',[],$e->getMessage(),(int)((microtime(true)-$start)*1000));$results[$name]=['error'=>$e->getMessage()];}}
        }finally{$this->repo->unlock();}
        return['locked'=>false,'tasks'=>$results];
    }
    private function absences():int{$date=gmdate('Y-m-d',strtotime('-1 day'));$weekday=(int)gmdate('N',strtotime($date));$count=0;foreach($this->repo->absenceCandidates($date)as$row){$days=json_decode((string)$row['applicable_days'],true)?:[];if(!in_array($weekday,array_map('intval',$days),true))continue;$start=$date.' '.$row['start_time'];$endDate=((string)$row['end_time']<=(string)$row['start_time'])?gmdate('Y-m-d',strtotime($date.' +1 day')):$date;if($this->repo->addAbsence($row,$date,$start,$endDate.' '.$row['end_time']))$count++;}return$count;}
    private function retention():array{$scheduled=0;$deleted=0;foreach($this->repo->retentionCandidates($this->repo->setting('retention.evidence_months',12),$this->repo->setting('retention.identification_days',90),$this->repo->setting('retention.warning_days',7))as$row)if($this->repo->scheduleRetention($row))$scheduled++;$storage=realpath($this->root.'/storage');foreach($this->repo->dueRetention()as$row){$candidate=realpath($this->root.'/'.ltrim(str_replace('\\','/',(string)$row['file_path']),'/'));if($storage&&$candidate&&str_starts_with(strtolower($candidate),strtolower($storage.DIRECTORY_SEPARATOR))&&is_file($candidate))unlink($candidate);$this->repo->deletedRetention((int)$row['id']);$deleted++;}return['scheduled'=>$scheduled,'deleted'=>$deleted];}
    private function cleanup():int{$dir=$this->root.'/storage/temp';if(!is_dir($dir))return 0;$count=0;foreach(new \FilesystemIterator($dir)as$file){if($file->isFile()&&$file->getMTime()<time()-86400&&unlink($file->getPathname()))$count++;}return$count;}
}
