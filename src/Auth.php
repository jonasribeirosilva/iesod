<?php namespace Iesod;

use Iesod\Database\Model;


class Auth {
    const USERGROUP_USER = 0;
    const USERGROUP_ADMIN = 1;
    
    static $AuthData;
    private $User;
    
    public function __construct(UserInterface $User){
        $this->User = $User;
    }
    /** Get user data authenticated | Pegar dados de usuário autenticado
     * 
     * @param boolean $force Force refresh
     * @return \Iesod\AuthUser|boolean False if fail
     */
    static function getUser($force = false){
        
        $sessionId = sessionId();
        
        if(
            !$force
            && !is_null(static::$AuthData)
            && isset(static::$AuthData['id_session'])
            && static::$AuthData['id_session']==$sessionId
        ){
            return ( new AuthUser( static::$AuthData ) );
        }
        
        $Model = new class() extends Model {
            protected $table = 'auth';
            protected $primaryKey = 'id_session';
        };
        
        try {
            $Model->update(
                ['last_access' => date('Y-m-d H:i:s',time())],
                $sessionId
            );
        } catch (\Exception $e) {
        }
        
        try {
            $result = $Model
                ->where('active','=',1)
                ->find( $sessionId );
            
            if($result===false)
                return false;
            
            static::$AuthData = $result;
            return ( new AuthUser( $result ) );
        } catch (\Exception $e) {
            return false;
        }
    }
    
    static function getUserId($default = null){
        $AuthUser = static::getUser();
        return ($AuthUser===false) ? $default : $AuthUser->getId() ;
    }
    
    static function getUserGroup($default = -1){
        $AuthUser = static::getUser();
        return ($AuthUser===false) ? $default : $AuthUser->getUserGroup() ;
    }
    /** Execute signIn
     * 
     * @param string $username
     * @param string $password
     * @throws AuthException
     * @return boolean
     */
    public function login($username,$password){
        $data = $this->User
            ->whereLogin($username)
            ->first();
        if($data==false){
            throw new AuthException(
                "User not found",
                AuthException::E_USER_NOT_FOUND
            );
            return false;
        }
        
        if($data['active']==0){//0 - No / 1 - Yes
            throw new AuthException(
                "User not active",
                AuthException::E_USER_UNVERIFICATION
            );
            return false;
        }
        
        if(!checkHash($password, $data['password'])){
            throw new AuthException(
                "Password invalid",
                AuthException::E_PASSWORD_INVALID
            );
            return false;
        }
        
        //SIGNIN ------------------
        $sessionId = sessionCreateId(true);
        $AuthData = [
            'id_session' => $sessionId,
            'id_user' => $data['id'],
            'active' => 1,
            'usergroup' => $data['usergroup']?? 0,
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone']
        ];
        
        $Model = new class($sessionId) extends Model{
            protected $table = 'auth';
            protected $primaryKey = 'id_session';
        };
        $Model->insert($AuthData, false);
        
        static::$AuthData = $AuthData;
        //SIGNIN ------------------
        return true;
    }
    /** Execute logout and Clear cookie of session
     * 
     */
    static public function logout(){
        $sessionId = sessionId();
        $Model = new class($sessionId) extends Model{
            protected $table = 'auth';
            protected $primaryKey = 'id_session';
        };
        $Model->update(['active' => 0], $sessionId);
        static::$AuthData = null;
        
        //Create new session
        sessionClose();
    }
}

/** Get user data authenticated | Pegar dados de usuário autenticado
 * 
 * @param boolean $force Force refresh
 * @return \Iesod\AuthUser|boolean False if fail
 */
function getUserAuth($force = false){
    return Auth::getUser($force);
}