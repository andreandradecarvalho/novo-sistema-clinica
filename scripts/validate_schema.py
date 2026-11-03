#!/usr/bin/env python3
"""Valida DDL e invariantes com dados sinteticos em banco LOCAL descartavel.
Padrao: credenciais DB_* de .env (nao versionado).
--container-root: administracao interna do conteiner local mariadb_container.
Nunca importa no banco da aplicacao; remove somente o banco criado pela execucao.
"""
from pathlib import Path
import subprocess,tempfile,os,json,uuid,re
ROOT=Path(__file__).resolve().parents[1]
os.chdir(ROOT)
v={}
for line in (Path('.env').read_text().splitlines() if Path('.env').exists() else []):
 if '=' in line and not line.lstrip().startswith('#'):
  k,x=line.split('=',1); v[k]=x.strip().strip('"').strip("'")
f=tempfile.NamedTemporaryFile(mode='w',prefix='precordis-local-',delete=False); os.chmod(f.name,0o600)
f.write('[client]\n')
for k,x in [('host','127.0.0.1'),('port',v.get('DB_PORT','3306')),('user',v.get('DB_USERNAME','usuario')),('password',v.get('DB_PASSWORD',''))]:
 if '\n' in x or '\r' in x: raise ValueError('Invalid parameter')
 f.write(k+'="'+x.replace('\\','\\\\').replace('"','\\"')+'"\n')
f.close();base=['mysql','--defaults-extra-file='+f.name,'--protocol=TCP','--batch','--skip-column-names']; db='novo_sistema_clinica_check_'+uuid.uuid4().hex[:10]; created=False
# Identificadores nao aceitam bind; esta allowlist limita o DDL ao banco efemero gerado.
assert re.fullmatch(r'novo_sistema_clinica_check_[0-9a-f]{10}', db)
import sys
if '--container-root' in sys.argv:
 base=['docker','exec','-i','mariadb_container','sh','-c','export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"; exec mariadb --user=root --batch --skip-column-names "$@"','mariadb-validation']
results=[]
def run(sql,database=False):
 return subprocess.run(base+(['--database='+db] if database else []),input=sql,capture_output=True,text=True)
def ok(name,sql,fail=False,code=None):
 r=run(sql,True); expected_code = code if code is not None else (4025 if 'MariaDB' in version else 3819)
 passed=(r.returncode!=0 and 'ERROR '+str(expected_code)+' ' in r.stderr) if fail else r.returncode==0
 results.append({'test':name,'passed':passed})
 if not passed: raise RuntimeError(name+': '+r.stderr[-1800:])
try:
 r=run('SELECT VERSION();');assert r.returncode==0,r.stderr
 version=r.stdout.strip();print('Servidor local:',version,flush=True)
 r=run('CREATE DATABASE `'+db+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');assert r.returncode==0,r.stderr;created=True
 ok('DDL com FKs habilitadas',Path('docs/schema/schema.sql').read_text())
 ok('Fixtures sinteticas de duas clinicas',"""
 INSERT INTO tenants(id,public_id,name,slug) VALUES(1,'00000000000000000000000001','Clinica sintetica A','synthetic-a'),(2,'00000000000000000000000002','Clinica sintetica B','synthetic-b');
 INSERT INTO companies(id,tenant_id,public_id,name,slug) VALUES(1,1,'00000000000000000000000003','Empregador ficticio A','employer-a'),(2,2,'00000000000000000000000004','Empregador ficticio B','employer-b');
 INSERT INTO units(id,tenant_id,public_id,name) VALUES(1,1,'00000000000000000000000005','Unidade A');
 INSERT INTO patients(id,tenant_id,public_id,name,normalized_name,company_id) VALUES(1,1,'00000000000000000000000006','Pessoa sintetica A','PESSOA SINTETICA A',1),(2,2,'00000000000000000000000007','Pessoa sintetica B','PESSOA SINTETICA B',2);
 INSERT INTO users(id,tenant_id,public_id,name,email,password) VALUES(1,1,'00000000000000000000000008','Operador sintetico','synthetic@example.invalid','NOT_AN_AUTHENTICATION_HASH');
 INSERT INTO professionals(id,tenant_id,user_id) VALUES(1,1,1);
 INSERT INTO exam_types(id,tenant_id,public_id,code,name) VALUES(1,1,'00000000000000000000000009','SYNTH','Exame sintetico');
 INSERT INTO appointments(id,tenant_id,public_id,patient_id,unit_id,scheduled_date) VALUES(1,1,'00000000000000000000000010',1,1,'2026-11-03');
 """)
 ok('Paciente nao referencia empresa de outra clinica','UPDATE patients SET company_id=2 WHERE id=1;',True,1452)
 ok('Reserva duplicada rejeitada',"INSERT INTO appointments(tenant_id,public_id,patient_id,unit_id,scheduled_date) VALUES(1,'00000000000000000000000011',1,1,'2026-11-03');",True,1062)
 ok('Cancelamento libera mesmo slot',"UPDATE appointments SET status='cancelado',canceled_at=NOW() WHERE id=1; INSERT INTO appointments(id,tenant_id,public_id,patient_id,unit_id,scheduled_date) VALUES(3,1,'00000000000000000000000012',1,1,'2026-11-03');")
 ok('Reserva em outro horario permitida',"INSERT INTO appointments(tenant_id,public_id,patient_id,unit_id,scheduled_date,scheduled_at) VALUES(1,'00000000000000000000000013',1,1,'2026-11-03','2026-11-03 10:00:00');")
 ok('Data e horario divergentes rejeitados',"INSERT INTO appointments(tenant_id,public_id,patient_id,scheduled_date,scheduled_at) VALUES(1,'00000000000000000000000014',1,'2026-11-03','2026-11-04 10:00:00');",True)
 ok('NPS acima de 10 rejeitado',"INSERT INTO surveys(tenant_id,public_id,nps) VALUES(1,'00000000000000000000000015',11);",True)
 ok('Excluir tenant com historico rejeitado','DELETE FROM tenants WHERE id=1;',True,1451)
 ok('Pedido, atendimento e duas tentativas',"""
 INSERT INTO exam_orders(id,tenant_id,public_id,patient_id,company_id) VALUES(1,1,'00000000000000000000000016',1,1);
 INSERT INTO exam_order_items(id,tenant_id,exam_order_id,exam_type_id,unit_price,description_snapshot) VALUES(1,1,1,1,100,'Exame sintetico');
 INSERT INTO visits(id,tenant_id,public_id,patient_id,appointment_id,exam_order_id,unit_id,checked_in_at) VALUES(1,1,'00000000000000000000000017',1,3,1,1,NOW());
 INSERT INTO exam_executions(id,tenant_id,visit_id,exam_order_item_id,exam_type_id,performed_by,attempt_no) VALUES(1,1,1,1,1,1,1),(2,1,1,1,1,1,2);
 INSERT INTO exam_results(id,tenant_id,public_id,patient_id,exam_execution_id) VALUES(1,1,'00000000000000000000000018',1,1);
 """)
 ok('Tentativa duplicada rejeitada','INSERT INTO exam_executions(tenant_id,visit_id,exam_type_id,attempt_no) VALUES(1,1,1,1);',True,1062)
 ok('Resultado de outro tenant rejeitado',"INSERT INTO exam_results(tenant_id,public_id,patient_id,exam_execution_id) VALUES(2,'00000000000000000000000019',2,1);",True,1452)
 ok('Medicao com unidade e referencia preservadas',"INSERT INTO measurement_types(id,tenant_id,code,name,unit) VALUES(1,1,'SYNTH_MEASURE','Medida sintetica','unit'); INSERT INTO exam_measurements(tenant_id,exam_result_id,measurement_type_id,value_numeric,unit_snapshot,reference_snapshot) VALUES(1,1,1,5,'unit','{\"min\":1,\"max\":10}');")
 ok('Medicao com dois tipos de valor rejeitada',"UPDATE exam_measurements SET value_text='incompatible' WHERE exam_result_id=1;",True)
 ok('Mensagem idempotente',"INSERT INTO messages(tenant_id,channel,to_address,idempotency_key) VALUES(1,'email','synthetic@example.invalid','synthetic-once');")
 ok('Retry duplicado da mensagem rejeitado',"INSERT INTO messages(tenant_id,channel,to_address,idempotency_key) VALUES(1,'email','synthetic@example.invalid','synthetic-once');",True,1062)
 ok('Vinculo e avaliacao ocupacional',"""
 INSERT INTO patient_company_links(id,tenant_id,patient_id,company_id,job_title,starts_on) VALUES(1,1,1,1,'Funcao sintetica','2026-01-01');
 INSERT INTO occupational_assessments(id,tenant_id,public_id,patient_id,employment_link_id,assessment_type,context_snapshot_encrypted) VALUES(1,1,'00000000000000000000000020',1,1,'periodic','SYNTHETIC_CIPHERTEXT_PLACEHOLDER');
 """)
 ok('Avaliacao completa sem medico rejeitada',"UPDATE occupational_assessments SET status='completed' WHERE id=1;",True)
 ok('Conclusao automatica invalida rejeitada',"UPDATE occupational_assessments SET conclusion='automatic' WHERE id=1;",True)
 ok('Conclusao medica completa registrada',"UPDATE occupational_assessments SET assessed_by=1,assessed_on='2026-11-03',conclusion='fit',completed_at=NOW(),status='completed' WHERE id=1;")
 ok('Documento e ASO versionados',"""
 INSERT INTO documents(id,tenant_id,public_id,document_group,patient_id,kind,title,path,checksum_sha256) VALUES(1,1,'00000000000000000000000021','00000000000000000000000022',1,'aso','ASO sintetico','synthetic/not-a-real-file',REPEAT('a',64));
 INSERT INTO aso_records(tenant_id,assessment_id,document_id,version_no,issued_by,issued_at,snapshot_encrypted) VALUES(1,1,1,1,1,NOW(),'SYNTHETIC_CIPHERTEXT_PLACEHOLDER');
 """)
 ok('Documento nao vincula paciente de outro tenant','UPDATE documents SET patient_id=2 WHERE id=1;',True,1452)
 ok('Perguntas de mesmo codigo em versoes diferentes',"INSERT INTO health_question_groups(id,tenant_id,code,name) VALUES(1,1,'SYNTH','Grupo sintetico'); INSERT INTO health_questions(tenant_id,group_id,code,label,version) VALUES(1,1,'Q1','Pergunta antiga',1),(1,1,'Q1','Pergunta nova',2);")
 ok('ETL referencia unica',"INSERT INTO migration_runs(id,tenant_id,public_id,importer_version) VALUES(1,1,'00000000000000000000000023','synthetic-test'); INSERT INTO legacy_references(tenant_id,source_db,source_table,source_id,target_type,target_id,row_hash,run_id) VALUES(1,'synthetic','patients','x','patient',1,REPEAT('b',64),1);")
 ok('ETL duplicado rejeitado',"INSERT INTO legacy_references(tenant_id,source_db,source_table,source_id,target_type,target_id,row_hash,run_id) VALUES(1,'synthetic','patients','x','patient',1,REPEAT('b',64),1);",True,1062)
 r=run("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE(); SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE();",True)
 print('Tabelas e FKs criadas:',r.stdout.strip().replace('\n',' / '),flush=True)
 print('Testes aprovados:',len(results),flush=True)
 Path('/tmp/precordis-schema-validation.json').write_text(json.dumps({'server':version,'tests':results,'ddl_sha256':__import__('hashlib').sha256(Path('docs/schema/schema.sql').read_bytes()).hexdigest()},indent=2))
finally:
 if created:
  r=run('DROP DATABASE `'+db+'`;');print('Banco descartavel removido:',r.returncode==0,flush=True)
 os.unlink(f.name)
