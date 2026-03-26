import unicodedata
from pathlib import Path

import mysql.connector
import bcrypt

path = Path("ativos.csv.xls")

def file_exists(file_path):
    if not file_path.exists():
        return 0
    return 1

def ler_linhas(file_path):
    # Encodings mais comuns no Brasil. O 'strict' força o erro se o formato não bater.
    encodings_para_tentar = ["windows-1252", "utf-8"]

    for enc in encodings_para_tentar:
        try:
            with open(file_path, "r", encoding=enc, errors="strict") as file:
                linhas = file.readlines()
            print(f"Arquivo lido com encoding: {enc}")
            return linhas
        except UnicodeDecodeError:
            print(f"Falhou com {enc}, tentando próximo...")

    # Último recurso
    print("Usando utf-8 com errors='replace' como último recurso.")
    with open(file_path, "r", encoding="utf-8", errors="replace") as file:
        return file.readlines()

def normalizar_texto(texto):
    # Mantém a sua lógica de normalização para evitar problemas no banco
    return unicodedata.normalize("NFC", texto)
        
def limpar_linhas(lines):
    clean_lines = []
    for line in lines:
        clean_line = normalizar_texto(line).replace('"', '').strip()
        if clean_line:
            clean_lines.append(clean_line)
    return clean_lines
        
def separar_linhas(lines):
    data = {}
    for line in lines:
        line = line.strip()
        
        # Ignora cabeçalhos, rodapés e linhas vazias de forma mais explícita
        if line.startswith("Funcionário") or line.startswith("Total") or not line:
            continue
            
        try:
            matricula, nome = line.split(';', 1)
            matricula = matricula.strip()
            nome = nome.strip('; ')
            
            if matricula.isdigit():
                data[matricula] = nome
        except ValueError:
            continue
            
    return data

def gerar_senha_hash(senha):
    salt = bcrypt.gensalt(rounds=10)
    hash_bytes = bcrypt.hashpw(senha.encode('utf-8'), salt)
    return hash_bytes.decode('utf-8').replace('$2b$', '$2y$')

if file_exists(path):
    lines = ler_linhas(path)
    lines = limpar_linhas(lines)
    dados_dict = separar_linhas(lines)

    print(f"Preparando hashes de senha para {len(dados_dict)} usuários...")
    
    valores_para_inserir = [
        (m, n, gerar_senha_hash(f"stcasa{m}")) 
        for m, n in dados_dict.items()
    ]

    try: 
        conexao = mysql.connector.connect(
            host="db", 
            port=3306,
            user="root", 
            password="snd1234", 
            database="refeitorio_snd"
        )
        cursor = conexao.cursor()
        
        sql = "INSERT IGNORE INTO usuarios (matricula, nome, senha) VALUES (%s, %s, %s)"
        cursor.executemany(sql, valores_para_inserir)
        conexao.commit()
        
        print(f"Sucesso! {cursor.rowcount} registros processados no banco.")

    except mysql.connector.Error as erro:
        print(f"Erro no mysql: {erro}")
    finally:
        if 'conexao' in locals() and conexao.is_connected():
            cursor.close()
            conexao.close()
else:
    print(f"Arquivo não encontrado em '{path}'")