import React, { useEffect, useRef } from 'react';
import { View, StyleSheet, Image, Animated, Dimensions, Text } from 'react-native';
import { useNavigation } from '@react-navigation/native';

const { width, height } = Dimensions.get('window');

export default function SplashScreen() {
    const navigation = useNavigation();

    // React Native Built-in Animated Values (Sıfır ek kütüphane gerekli değil)
    const bgOpacity = useRef(new Animated.Value(0)).current;
    const logoOpacity = useRef(new Animated.Value(0)).current;
    const logoScale = useRef(new Animated.Value(0.6)).current;
    const textOpacity = useRef(new Animated.Value(0)).current;
    const glowPulse = useRef(new Animated.Value(0.3)).current;

    useEffect(() => {
        // 1) Arka plan fade-in (0-1.5sn)
        Animated.timing(bgOpacity, {
            toValue: 1,
            duration: 1500,
            useNativeDriver: true,
        }).start();

        // 2) Logo fade-in + scale-up (0.5-3sn)
        Animated.parallel([
            Animated.timing(logoOpacity, {
                toValue: 1,
                duration: 2000,
                delay: 500,
                useNativeDriver: true,
            }),
            Animated.spring(logoScale, {
                toValue: 1,
                tension: 10,
                friction: 3,
                delay: 500,
                useNativeDriver: true,
            }),
        ]).start();

        // 3) Yazı fade-in (2-4sn)
        Animated.timing(textOpacity, {
            toValue: 1,
            duration: 1500,
            delay: 2000,
            useNativeDriver: true,
        }).start();

        // 4) Neon glow pulse (sürekli döngü)
        Animated.loop(
            Animated.sequence([
                Animated.timing(glowPulse, {
                    toValue: 1,
                    duration: 1500,
                    useNativeDriver: true,
                }),
                Animated.timing(glowPulse, {
                    toValue: 0.3,
                    duration: 1500,
                    useNativeDriver: true,
                }),
            ])
        ).start();

        // 5) 7 saniye sonra Login ekranına geçiş
        const timer = setTimeout(() => {
            navigation.replace('Login');
        }, 7000);

        return () => clearTimeout(timer);
    }, []);

    return (
        <View style={styles.container}>
            {/* Arka Plan Görseli */}
            <Animated.Image
                source={require('../../assets/splash.jpg')}
                style={[styles.backgroundImage, { opacity: bgOpacity }]}
                resizeMode="cover"
                blurRadius={4}
            />

            {/* Koyu Overlay */}
            <View style={styles.overlay}>

                {/* Logo Container */}
                <Animated.View style={[
                    styles.logoContainer,
                    {
                        opacity: logoOpacity,
                        transform: [{ scale: logoScale }],
                    }
                ]}>
                    {/* Neon Glow Halkası */}
                    <Animated.View style={[styles.glowRing, { opacity: glowPulse }]} />
                    
                    <Image
                        source={require('../../assets/logo.jpg')}
                        style={styles.logo}
                        resizeMode="contain"
                    />
                </Animated.View>

                {/* Uygulama Adı */}
                <Animated.View style={{ opacity: textOpacity, marginTop: 30 }}>
                    <Text style={styles.appName}>FiloTakip</Text>
                    <Text style={styles.appSubtitle}>Araç Takip Sistemi</Text>
                </Animated.View>

                {/* Alt kısımdaki yükleniyor çubuğu */}
                <Animated.View style={[styles.loadingContainer, { opacity: textOpacity }]}>
                    <View style={styles.loadingBarBg}>
                        <Animated.View style={[styles.loadingBarFill, { opacity: glowPulse }]} />
                    </View>
                </Animated.View>
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#0a0e1a',
    },
    backgroundImage: {
        position: 'absolute',
        width: width,
        height: height,
    },
    overlay: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: 'rgba(10, 14, 26, 0.75)',
    },
    logoContainer: {
        width: 180,
        height: 180,
        borderRadius: 36,
        overflow: 'hidden',
        alignItems: 'center',
        justifyContent: 'center',
    },
    glowRing: {
        position: 'absolute',
        width: 210,
        height: 210,
        borderRadius: 42,
        borderWidth: 2,
        borderColor: '#38bdf8',
        shadowColor: '#38bdf8',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 0.8,
        shadowRadius: 25,
    },
    logo: {
        width: 180,
        height: 180,
        borderRadius: 36,
    },
    appName: {
        fontSize: 32,
        fontWeight: '900',
        color: '#ffffff',
        textAlign: 'center',
        letterSpacing: 2,
    },
    appSubtitle: {
        fontSize: 14,
        fontWeight: '600',
        color: '#64748b',
        textAlign: 'center',
        marginTop: 6,
        letterSpacing: 4,
        textTransform: 'uppercase',
    },
    loadingContainer: {
        position: 'absolute',
        bottom: 80,
        width: 200,
        alignItems: 'center',
    },
    loadingBarBg: {
        width: 200,
        height: 3,
        backgroundColor: 'rgba(56, 189, 248, 0.15)',
        borderRadius: 2,
        overflow: 'hidden',
    },
    loadingBarFill: {
        width: '100%',
        height: 3,
        backgroundColor: '#38bdf8',
        borderRadius: 2,
    },
});
