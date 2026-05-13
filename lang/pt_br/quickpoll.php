<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese language strings for mod_quickpoll.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['additionalresponses'] = '+{$a} a mais';
$string['allowmultiple'] = 'Permitir múltiplas escolhas por pergunta';
$string['allowmultiple_help'] = 'Quando ativado, o aluno pode selecionar mais de uma opção por pergunta.';
$string['anonymous'] = 'Votação anônima';
$string['anonymous_help'] = 'Quando ativado, o aluno pode optar por ocultar sua identidade dos colegas. O ID do usuário é sempre armazenado no servidor para evitar votos duplicados.';
$string['anonymousdisabled'] = 'Desabilitado — todas as respostas são sempre identificadas';
$string['anonymousoptin'] = 'Opt-in — o aluno pode escolher ficar anônimo';
$string['answered'] = 'Respondida';
$string['answeredby'] = 'Respondida por {$a} alunos';
$string['answersnow'] = '{$a} respostas até agora';
$string['closingon'] = 'Encerra em {$a}';
$string['errornoquestions'] = 'Você deve adicionar pelo menos uma pergunta antes de salvar.';
$string['errorperiod'] = 'O período de votação encerrou ou ainda não começou.';
$string['errorvoteduplicate'] = 'Você já votou nesta enquete.';
$string['live'] = 'Ao vivo';
$string['maxgrade'] = 'Nota por responder';
$string['maxgrade_help'] = 'O aluno recebe esta nota automaticamente ao enviar todas as respostas. Defina 0 para desabilitar a avaliação.';
$string['modulename'] = 'Enquete Rápida';
$string['modulename_help'] = 'A atividade Enquete Rápida permite ao professor criar uma ou mais perguntas com opções predefinidas. Os alunos votam diretamente na página do curso e veem os resultados em tempo real, de forma semelhante às enquetes do WhatsApp.';
$string['modulenameplural'] = 'Enquetes Rápidas';
$string['noanswersyet'] = 'Nenhuma resposta ainda.';
$string['openingon'] = 'Abre em {$a}';
$string['optionanonymous'] = 'Ocultar minha identidade nesta resposta';
$string['pluginadministration'] = 'Administração da Enquete Rápida';
$string['pluginname'] = 'Enquete Rápida';
$string['pointsbadge'] = '{$a} pontos';
$string['pollclosed'] = 'Esta enquete está encerrada.';
$string['pollnotopen'] = 'Esta enquete ainda não está aberta.';
$string['privacy:metadata:quickpoll_answers'] = 'Registra cada voto enviado pelo aluno, incluindo se o aluno escolheu ser anônimo.';
$string['privacy:metadata:quickpoll_answers:anonymous'] = 'Se o aluno solicitou anonimato para esta resposta.';
$string['privacy:metadata:quickpoll_answers:optionid'] = 'A opção escolhida pelo aluno.';
$string['privacy:metadata:quickpoll_answers:pollid'] = 'A enquete a qual esta resposta pertence.';
$string['privacy:metadata:quickpoll_answers:questionid'] = 'A pergunta à qual esta resposta se refere.';
$string['privacy:metadata:quickpoll_answers:timecreated'] = 'O momento em que a resposta foi enviada.';
$string['privacy:metadata:quickpoll_answers:userid'] = 'O usuário que enviou a resposta.';
$string['questionlabel'] = 'Pergunta {$a}';
$string['questionsheader'] = 'Perguntas';
$string['quickpoll:addinstance'] = 'Adicionar uma atividade Enquete Rápida';
$string['quickpoll:manage'] = 'Gerenciar atividades Enquete Rápida';
$string['quickpoll:viewanonymousresults'] = 'Ver a identidade por trás dos votos anônimos';
$string['quickpoll:viewresults'] = 'Ver resultados da enquete';
$string['quickpoll:vote'] = 'Enviar um voto';
$string['resultshiddenuntilclose'] = 'Os resultados serão exibidos após o encerramento da enquete.';
$string['resultshiddenuntilvote'] = 'Os resultados serão exibidos após você votar.';
$string['showresults'] = 'Exibir resultados';
$string['showresults_afterclose'] = 'Após o encerramento da enquete';
$string['showresults_aftervote'] = 'Após o aluno votar';
$string['showresults_always'] = 'Sempre';
$string['showresults_help'] = 'Controla quando os alunos podem ver os resultados agregados.';
$string['timeclose'] = 'Data de encerramento';
$string['timeclose_help'] = 'Data e hora após as quais a votação não é mais aceita. Deixe em 0 para manter a enquete aberta indefinidamente.';
$string['timeopen'] = 'Data de abertura';
$string['timeopen_help'] = 'Data e hora a partir das quais os alunos podem votar. Deixe em 0 para abrir imediatamente.';
$string['votesubmitted'] = 'Seu voto foi registrado.';
$string['votingperiod'] = 'Período de votação';
