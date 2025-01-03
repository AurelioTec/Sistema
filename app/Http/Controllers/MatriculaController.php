<?php

namespace App\Http\Controllers;

use App\Helpers\Funcoes;
use App\Models\Funcionarios;
use App\Models\Inscricao;
use App\Models\matricula;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class MatriculaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = Auth::id();
        $funcionario = Funcionarios::where('Users_id', $userId)->first(); // Acessa o funcionário relacionado
        $matriculados = Matricula::with('inscricao', 'turma', 'usuario')->get();
        return view('pages.matricula', compact('matriculados', 'funcionario'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function show($id) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        $matricula = null;
        if (isset($request->id)) {
            //procurar um elemento no banco de dados usar o find
            $matricula = Matricula::find($request->id);
        } else {
            $matricula = new Matricula();
        }
        // Obter o ano atual
        $anoAtual = date('Y');
        $inicialNome = strtoupper(substr($request->nomeAluno, 0, 1));
        // Obter o maior número de matrícula existente no ano e inicial fornecidos
        $ultimoNumMatricula = Matricula::where('numatricula', 'like', "{$anoAtual}{$inicialNome}%")
            ->orderBy('numatricula', 'desc')
            ->value('numatricula');

        $nunmatricula = Funcoes::gerarNumeroMatricula($request->nomeAluno, $ultimoNumMatricula);
        if (isset($request->anexo)) {
            $request->validate([
                'anexo' => 'required|file|mimes:pdf|max:2048', // 2048 KB = 2MB
            ]);
            $doc = $request->file('anexo');
            $extencao = $doc->extension();
            // Obter a inicial do nome do aluno
            $docNome = md5($doc->getClientOriginalName() . strtotime('now')) . $extencao;
            $caminho = Storage::makeDirectory(public_path('docs/upload/aluno/' . $inicialNome . '/'));
            $doc->storeAs($caminho, $docNome);
            $matricula->anexo = $docNome;
        }
        $matricula->inscricaos_id = $request->alunoId;
        $matricula->turmas_id = $request->turma;
        $matricula->lestrangeira = $request->lestrangeira;
        $matricula->encarregado = $request->encarregado;
        $matricula->telfencarregado = $request->telfencarregado;
        $matricula->tipomatricula = $request->tipomatricula;
        $matricula->estado = "Ativo";
        $matricula->numatricula = $nunmatricula;
        $matricula->datamatricula = Carbon::now()->toDateString();
        $matricula->users_id = $userId;
        // Encontrar a inscrição pelo ID
        $inscricao = Inscricao::find($request->alunoId);

        if (!$inscricao) {
            Alert::error('Error', 'Aluno não encontrado');
            return redirect()->back();
        } else {
            // Alterar o estado da inscrição para "matriculado"
            $inscricao->estado = 'Matriculado';
            $inscricao->save();  // Salvando a inscrição com o novo estado
            $matricula->save();
            if ($matricula) {
                if (isset($request->id)) {
                    Alert::success('Sucesso', 'Dados do aluno atualizados');
                    return redirect()->back();
                } else {
                    $lastId = $matricula->id;

                    if (!$lastId) {
                        throw new \Exception('ID da matrícula não encontrado');
                    }

                    $ano = $request->anoletivo;

                    $aluno = Matricula::with(['inscricao.municipios', 'turma', 'usuario'])
                        ->where('id', $lastId)
                        ->whereHas('turma', function ($query) use ($ano) {
                            $query->where('anolectivo', $ano);
                        })
                        ->first();

                    if (!$aluno) {
                        throw new \Exception('Aluno não encontrado com os filtros especificados');
                    }

                    dd($aluno);

                    Alert::success('Sucesso', 'Aluno inscrito com sucesso');
                    return view('relatorios.fichaaluno', compact('aluno'));
                }
            } else {
                Alert::error('Error', 'Erro ao inscrever o aluno');
                return redirect()->back();
            }
        }
    }
}
