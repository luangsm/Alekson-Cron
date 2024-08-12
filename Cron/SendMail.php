<?php
/**
 * Copyright © HudsonAlves All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Alekseon\CronMail\Cron;

class SendMail
{

    protected $logger;

    protected $objectManager;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/CronMail.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $this->logger = $logger;
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        try{            
            $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            
            // orderm: entity_id
            // status: enviado
            // MailSended
            // alekseon_custom_form_record_attribute
            // alekseon_custom_form_record_entity_varchar

            $sql="
                SELECT 
                    av.entity_id, av.value email, (
                        SELECT s_av.value 
                        FROM alekseon_custom_form_record_entity_varchar s_av
                        JOIN
                        alekseon_custom_form_record_attribute s_at
                            ON (s_at.frontend_label = 'Nome' AND s_at.id = s_av.attribute_id)
                        LEFT OUTER JOIN MailSended s_ms 
                                ON (s_av.entity_id = s_ms.ordem)
                        WHERE s_ms.ordem is null
                            AND av.entity_id = s_av.entity_id
                    ) name,
                    (
                        SELECT s_av.value 
                        FROM alekseon_custom_form_record_entity_varchar s_av
                        JOIN
                        alekseon_custom_form_record_attribute s_at
                            ON (s_at.frontend_label = 'Telefone' AND s_at.id = s_av.attribute_id)
                        LEFT OUTER JOIN MailSended s_ms 
                                ON (s_av.entity_id = s_ms.ordem)
                        WHERE s_ms.ordem is null
                            AND av.entity_id = s_av.entity_id
                    ) telephone,
                    (
                        SELECT s_av.value 
                        FROM alekseon_custom_form_record_entity_varchar s_av
                        JOIN
                        alekseon_custom_form_record_attribute s_at
                            ON (s_at.frontend_label = 'O que está pensando?' AND s_at.id = s_av.attribute_id)
                        LEFT OUTER JOIN MailSended s_ms 
                                ON (s_av.entity_id = s_ms.ordem)
                        WHERE s_ms.ordem is null
                            AND av.entity_id = s_av.entity_id
                    ) comment
                FROM 
                    alekseon_custom_form_record_entity_varchar av
                JOIN
                    alekseon_custom_form_record_attribute at
                        ON (
                            at.frontend_label = 'E-mail'
                            AND at.id = av.attribute_id
                        )
                LEFT OUTER JOIN
                    MailSended ms 
                        ON (av.entity_id = ms.ordem)
                WHERE
                    ms.ordem is null
                GROUP BY av.entity_id, av.value
                ;
            ";

            $connection->query($sql);
            $result = $connection->fetchAll($sql);

            $this->logger->info(json_encode($result, JSON_PRETTY_PRINT));

            if($result != null){                
                foreach($result as $row){    
                    if($this->enviarEmail($row['email'], $row['comment'], $row['name'], $row['telephone'])){
                        $sql = "INSERT INTO MailSended(ordem, status) VALUES('".$row['entity_id']."', 'enviado');";
                        $connection->query($sql);
                    }
                }
            }
        }catch(\Exception $e){
            $this->logger->info("Exception: ".$e->getMessage());
        }
    }

    public function enviarEmail($email, $comment, $name, $telephone){
        try{

            $this->logger->info("entrou enviar email");

            $transport = $this->objectManager->create('Magento\Framework\Mail\Template\TransportBuilder'); 
            $templateVars = [
                'email'     => $email,
                'comment'   => $comment,
                'name'      => $name,
                'telephone' => $telephone
            ];
            $data = $transport
                ->setTemplateIdentifier(3)
                ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => 1])
                ->setTemplateVars(['data' => $templateVars])
                ->setFrom(['name' => 'F.A Colchões','email' => 'naoresponda@facolchoes.com.br'])
                ->addTo(['lf@famaringa.com.br'])
                ->getTransport();
            $data->sendMessage();

            $this->logger->info("finalizou enviar email");

            return true;
        }catch(\Exception $e){
            $this->logger->info("Exception enviarEmail: ".$e->getMessage());
            return false;
        }
    }
}