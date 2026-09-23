import React, { useEffect, useRef, useState, useContext } from 'react';
import { View, Text, StyleSheet, Animated, Easing, Dimensions, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AuthContext } from '../context/AuthContext';
import SpaceWaves from '../components/SpaceWaves';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';

const { width } = Dimensions.get('window');

// Gelen metin tamamı büyük harfle yazılmışsa (Örn: MEHMET ÇELEBİ), önce küçük harfe çevirip sonra baş harflerini büyüten yardımcı fonksiyon
const toTitleCase = (str) => {
    if (!str) return '';
    return str
        .toLocaleLowerCase('tr-TR')
        .split(' ')
        .map(word => word.charAt(0).toLocaleUpperCase('tr-TR') + word.slice(1))
        .join(' ');
};

export default function LoginTransitionScreen({ onFinish }) {
    const { userInfo } = useContext(AuthContext);
    const [progress, setProgress] = useState(0);
    
    const progressAnim = useRef(new Animated.Value(0)).current;
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideAnim = useRef(new Animated.Value(20)).current;

    useEffect(() => {
        // Fade in text elements
        Animated.parallel([
            Animated.timing(fadeAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
            Animated.timing(slideAnim, { toValue: 0, duration: 800, easing: Easing.out(Easing.ease), useNativeDriver: true })
        ]).start();

        Animated.timing(progressAnim, {
            toValue: 100,
            duration: 4500,
            easing: Easing.bezier(0.25, 0.1, 0.25, 1),
            useNativeDriver: false
        }).start(() => {
            setTimeout(() => {
                if(onFinish) onFinish();
            }, 500);
        });

        // 4.5 saniye = 4500ms. 1'den 100'e saymak için 4500/100 = 45ms'de bir artırıyoruz.
        let currentPercent = 0;
        const interval = setInterval(() => {
            currentPercent += 1;
            setProgress(currentPercent);
            if (currentPercent >= 100) {
                clearInterval(interval);
            }
        }, 45);

        return () => clearInterval(interval);
    }, []);

    const barWidth = progressAnim.interpolate({
        inputRange: [0, 100],
        outputRange: ['0%', '100%']
    });

    return (
        <View style={styles.container}>
            <SpaceWaves />
            
            <Animated.View style={[styles.content, { opacity: fadeAnim, transform: [{ translateY: slideAnim }] }]}>
                
                {/* Logo and Icon */}
                <View style={styles.logoIcon}>
                    <Image 
                        source={require('../../assets/icon.png')} 
                        style={{ width: 140, height: 140, borderRadius: 36 }} 
                        resizeMode="cover" 
                    />
                </View>

                {/* Company Name */}
                <Text style={styles.companyName}>
                    {toTitleCase(userInfo?.company_name || 'ServisPilot')}
                </Text>

                {/* Welcome Message */}
                <Text style={styles.welcomeText}>
                    Hoş Geldin, <Text style={styles.userName}>{userInfo?.name?.split(' ')[0]}</Text>
                </Text>

            </Animated.View>

            <SafeAreaView style={styles.bottomArea} edges={['bottom']}>
                <Text style={styles.loadingText}>PROGRAMINIZ HAZIRLANIYOR...</Text>
                
                <View style={styles.progressContainer}>
                    <Animated.View style={[styles.progressBar, { width: barWidth }]} />
                </View>
                
                <Text style={styles.percentageText}>%{progress}</Text>
            </SafeAreaView>

        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#020617',
        alignItems: 'center',
        justifyContent: 'center',
    },
    content: {
        alignItems: 'center',
        marginTop: -50,
    },
    logoIcon: {
        width: 140,
        height: 140,
        backgroundColor: '#020617',
        borderRadius: 36,
        alignItems: 'center',
        justifyContent: 'center',
        shadowColor: '#8B5CF6',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.6,
        shadowRadius: 20,
        elevation: 15,
        marginBottom: 30,
    },
    companyName: {
        fontSize: 28,
        fontWeight: '900',
        color: '#ffffff',
        letterSpacing: 0.5,
        marginBottom: 8,
        textAlign: 'center',
        paddingHorizontal: 20,
    },
    welcomeText: {
        fontSize: 18,
        color: '#94A3B8',
        fontWeight: '500',
    },
    userName: {
        color: '#8B5CF6',
        fontWeight: '800',
    },
    bottomArea: {
        position: 'absolute',
        bottom: 80,
        width: '100%',
        paddingHorizontal: 40,
        alignItems: 'center',
    },
    loadingText: {
        color: '#64748B',
        fontSize: 12,
        fontWeight: '800',
        letterSpacing: 2,
        marginBottom: 16,
    },
    progressContainer: {
        width: '100%',
        height: 6,
        backgroundColor: 'rgba(255,255,255,0.1)',
        borderRadius: 10,
        overflow: 'hidden',
        marginBottom: 12,
    },
    progressBar: {
        height: '100%',
        backgroundColor: '#8B5CF6',
        borderRadius: 10,
        shadowColor: '#8B5CF6',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 1,
        shadowRadius: 10,
    },
    percentageText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: '900',
        fontVariant: ['tabular-nums'],
    }
});
